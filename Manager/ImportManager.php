<?php
/**
 * User: emreyilmaz
 */

namespace Delirehberi\ImportBundle\Manager;


use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Class ImportManager
 * @package Delirehberi\ImportBundle\Manager
 */
class ImportManager
{
    /** @var ConnectionFactory */
    private $connectionFactory;
    /** @var EntityManager */
    private $em;
    /** @var LoggerInterface */
    private $logger;
    /** @var array */
    private $maps;
    /** @var \Symfony\Component\PropertyAccess\PropertyAccessor */
    private $accessor;
    /** @var  Connection */
    private $connection;
    /** @var bool */
    private $debug = FALSE;
    /** @var ContainerInterface */
    private $container;
    /** @var int */
    private $offset = 0;
    /** @var int */
    private $limit = 100;
    private $update = TRUE;
    /**
     * ImportManager constructor.
     * @param ConnectionFactory $connectionFactory
     * @param EntityManager $entityManager
     * @param LoggerInterface $loggerInterface
     * @param array $maps
     */
    public function __construct(ConnectionFactory $connectionFactory, EntityManager $entityManager, LoggerInterface $loggerInterface, ContainerInterface $containerInterface, array $maps)
    {
        $this->connectionFactory = $connectionFactory;
        $this->em = $entityManager;
        $this->logger = $loggerInterface;
        $this->maps = $maps;
        $this->update = $containerInterface->getParameter('delirehberi_import.update');
        $this->accessor = PropertyAccess::createPropertyAccessor();
        $this->container = $containerInterface;
        $this->em->getConnection()->getConfiguration()->getSQLLogger(null);

    }


    public function startImport(OutputInterface &$output, $map_key = null, $entity_key = null)
    {
        try {
            $this->debug && $this->logger->info("Import started.");
            if ($map_key) {
                if (!isset($this->maps[$map_key])) {
                    throw new \Exception("$map_key not found");
                }
                $config = $this->maps[$map_key];
                $this->singleImport($config, $output, $entity_key);
            } else {
                foreach ($this->maps as $connection_key => $config) {
                    $this->singleImport($config, $output);
                }
            }

            $this->debug && $this->logger->info("Import completed.");
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), [
                "file" => $e->getFile(),
                "line" => $e->getLine(),
            ]);
        }
    }

    private function singleImport($config, OutputInterface &$output, $entity_key = null)
    {
        $this->connection = $this->connectionFactory->createConnection($config['database']);
        $this->connection->getConfiguration()->getSQLLogger(null);

        if ($entity_key) {
            if (!isset($config['maps'][$entity_key])) {
                throw new \Exception("Entity alias not found: " . $entity_key);
            }
            $map = $config['maps'][$entity_key];
            if (!$this->container->has($map['old_data']['service_id'])) {
                throw new \Exception("Service not exists: " . $map['old_data']['service_id']);
            }
            $result = $this->importEntity($map);
            $output->writeln("<info>Total " . count($result) . " $entity_key imported </info>");
        } else {
            foreach ((array)$config['maps'] as $key => $map) {

                if (!$this->container->has($map['old_data']['service_id'])) {
                    throw new \Exception("Service not exists: " . $map['old_data']['service_id']);
                }
                $offset = 0;
                do {
                    $result = $this->importEntity($map);
                    $output->writeln("<info>Total " . count($result) . " $key imported </info>");

                    if (!$result) {
                        break;
                    }

                    $offset++;

                    $this->setOffset($offset);

                } while (true);
            }
        }

        $this->connection->close();
    }

    private function importEntity($map)
    {
        $old_data_service = $this->container->get($map['old_data']['service_id']);

        if (!method_exists($old_data_service, $map['old_data']['method'])) {
            throw new \Exception("Method not found in service. Service: " . $map['old_data']['service_id'] . " , method: " . $map['old_data']['method']);
        }
        $old_data = call_user_func_array([
            $old_data_service, $map['old_data']['method']
        ], [
            $this->connection, $this->getOffset(), $this->getLimit()
        ]);
        $result = $this->mapping($old_data, $map);
        return $result;
    }

    private function mapping(array $old_data, array $map)
    {
        $this->debug && $this->logger->info("Mapping started.");
        $data = [];
        foreach ($old_data as $item) {
            $newItem = $this->getItem($map, $item);
            if($newItem->getId() && $this->isUpdate()===FALSE){
                continue;
            }
            foreach ($item as $key => $value) {
                $this->equalise($map, $key, $value, $newItem);
            }
            $data[] = $newItem;
            $this->em->persist($newItem);
        }
        $this->em->flush();

        $this->debug && $this->logger->info("Mapping completed.");
        return $data;
    }

    private function getItem($map, $item)
    {
        if (isset($map['fields']['old_id'])) {//is checkable
            $repositoryClass = $this->em->getRepository($map['entity']);
            $oldIdColumnName = $map['fields']['old_id']['name'];
            $existing_item = $repositoryClass->findOneBy(['old_id' => $item[$oldIdColumnName]]);
            if ($existing_item) {
                $this->debug
                && $this->logger->notice("Item existing.", ['entity' => $map['entity'], 'id' => $existing_item->getId()]);
                return $existing_item;
            }
        }
        return new $map['entity'];
    }

    private function equalise($map, $key, $value, &$item)
    {
        $this->debug && $this->logger->info("Equalising started.");
        if (!array_key_exists('fields', $map)) {
            throw new ParameterNotFoundException("Fields parameter not found in mapping");
        }
        foreach ($map['fields'] as $k => $options) {
            if ($options['name'] == $key || (is_array($options['name']) && in_array($key, $options['name']))) {
                $this->setValue($item, $k, $value, $options);
            }
        }
        $this->debug && $this->logger->info("Equalising completed.");
    }

    private function setValue(&$item, $key, $value, $options = [])
    {

        try {
            $this->debug && $this->logger->info("Value adding is started.",[$key,$value,$options]);
            switch ($options['type']) {
                case "string":
                case "text":
                case "integer":
                case "collection":
                case "bool":
                    if (isset($options['modifier'])) {
                        $value = $this->modify($value, $options['modifier'], $item);
                    }
                    break;
                case "date":
                    if (isset($options['modifier'])) {
                        $value = $this->modify($value, $options['modifier'], $item);
                    } else {
                        $value = $this->modifyDate($value, $options);
                    }
                    break;
                case "object":
                    $value = $this->setObjectValue($item, $key, $value, $options);
                    break;
            }

            if (array_key_exists('value', $options)) {
                $value = $options['value'];
            }

            if(!isset($options['mapped']) || (isset($options['mapped']) && $options['mapped']!==false)){
                $value ?
                    $this->accessor->setValue($item, $key, $value) :
                    $this->logger->alert("Value is null for $key.");
            }else{
                $this->debug && $this->logger->info("Value not mapped");
            }

            $this->debug && $this->logger->info("Value adding is completed.");

        } catch (\Exception $e) {
            $this->logger->critical("Unexpected error",[
                'message'=>$e->getMessage(),
                'file'=>$e->getFile(),
                'line'=>$e->getLine()
            ]);
        }
    }

    private function modifyDate($value, $options)
    {

        if (!array_key_exists('format', $options)) {
            $options['format'] = "Y-m-d H:i:s";
        }

        $original_value = $value;
        $value = $value ? \DateTime::createFromFormat($options['format'], $value) : new \DateTime();
        if ($value === false) {
            throw new \Exception(json_encode(array_merge(
                \DateTime::getLastErrors(),
                [
                    'format' => $options['format'],
                    'value' => $original_value,
                    'date' => (new \DateTime())->format($options['format'])
                ]
            )));
        }
        return $value;
    }

    private function modify($value, $options, &$item)
    {
        if (!$this->container->has($options['service_id'])) {
            throw new \Exception("Service not exists: " . $options['service_id']);
        }

        $modifier = $this->container->get($options['service_id']);

        if (!method_exists($modifier, $options['method'])) {
            throw new \Exception("Method not found in service. Service: " . $options['service_id'] . " , method: " . $options['method']);
        }
        $value = call_user_func_array([$modifier, $options['method']], [
            $value, &$item, $this->connection, &$this->em
        ]);

        return $value;
    }

    public function setObjectValue(&$item, $key, $value, $options)
    {
        if (!$value) {
            return false;
        }

        $object = $this->accessor->getValue($item, $key);
        if (!$object and array_key_exists('entity', $options)) {
            $object = new $options['entity'];
        } elseif (!$object and array_key_exists('modifier', $options)) {
            $object = $this->modify($value, $options['modifier'], $item);

        }

        if (array_key_exists('fields', $options)) {
            $names = array_map(function ($fields) {
                return $fields['name'];
            }, $options['fields']);
            $this->debug && $this->logger->info("Multiple field names", $names);
            $this->equalise($options, $key, $value, $object);
        }
        $this->em->persist($object);
        return $object;
    }

    public function setDebug($debug)
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @param int $limit
     * @return ImportManager
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @param int $offset
     * @return ImportManager
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isUpdate()
    {
        return $this->update;
    }

    /**
     * @param boolean $update
     */
    public function setUpdate($update)
    {
        $this->update = $update;
        return $this;
    }



}