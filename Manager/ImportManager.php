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
        $this->accessor = PropertyAccess::createPropertyAccessor();
        $this->container = $containerInterface;
    }


    public function startImport(OutputInterface &$output, $map_key = null)
    {
        try {
            $this->debug && $this->logger->info("Import started.");
            if ($map_key) {
                if(!isset($this->maps[$map_key])){
                    throw new \Exception("$map_key not found");
                }
                $config = $this->maps[$map_key];
                $this->singleImport($config, $output);
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
                "code" => $e->getCode()
            ]);
        }
    }

    private function singleImport($config, OutputInterface &$output)
    {
        $this->connection = $this->connectionFactory->createConnection($config['database']);

        foreach ((array)$config['maps'] as $key => $map) {

            if (!$this->container->has($map['old_data']['service_id'])) {
                throw new \Exception("Service not exists: " . $map['old_data']['service_id']);
            }

            $old_data_service = $this->container->get($map['old_data']['service_id']);

            if (!method_exists($old_data_service, $map['old_data']['method'])) {
                throw new \Exception("Method not found in service. Service: " . $map['old_data']['service_id'] . " , method: " . $map['old_data']['method']);
            }

            $old_data = call_user_func_array([$old_data_service, $map['old_data']['method']], [$this->connection]);
            $result = $this->mapping($old_data, $map);
            $output->writeln("Total imported $key " . count($result));
        }

        $this->connection->close();
    }

    private function mapping(array $old_data, array $map)
    {
        $this->debug && $this->logger->info("Mapping started.");
        $data = [];
        foreach ($old_data as $item) {
            $newItem = new $map['entity'];
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
        $this->debug && $this->logger->info("Value adding is started.");
        switch ($options['type']) {
            case "string":
            case "text":
            case "integer":
                break;
            case "collection":

                if (!$this->container->has($options['modifier']['service_id'])) {
                    throw new \Exception("Service not exists: " . $options['modifier']['service_id']);
                }

                $modifier = $this->container->get($options['modifier']['service_id']);

                if (!method_exists($modifier, $options['modifier']['method'])) {
                    throw new \Exception("Method not found in service. Service: " . $options['modifier']['service_id'] . " , method: " . $options['modifier']['method']);
                }

                $value = call_user_func_array([$modifier, $options['modifier']['method']], [
                    $value, &$item, $this->connection, &$this->em
                ]);

                break;
            case "date":
                if (!array_key_exists('format', $options)) {
                    $options['format'] = "Y-m-d H:i:s";
                }
                $value = $value ? \DateTime::createFromFormat($options['format'], $value) : new \DateTime();
                break;
            case "bool":
                break;
            case "object":
                $value = $this->setObjectValue($item, $key, $value, $options);
                break;
        }

        if (array_key_exists('value', $options)) {
            $value = $options['value'];
        }

        $value ?
            $this->accessor->setValue($item, $key, $value) :
            $this->logger->alert("Value is null for $key.");

        $this->debug && $this->logger->info("Value adding is completed.");
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
            if (!$this->container->has($options['modifier']['service_id'])) {
                throw new \Exception("Service not exists: " . $options['modifier']['service_id']);
            }

            $modifier_service = $this->container->get($options['modifier']['service_id']);

            if (!method_exists($modifier_service, $options['modifier']['method'])) {
                throw new \Exception("Method not found in service. Service: " . $options['modifier']['service_id'] . " , method: " . $options['modifier']['method']);
            }

            $object = call_user_func_array([$modifier_service, $options['modifier']['method']], [
                $value, &$item, $this->connection, &$this->em
            ]);
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
}