<?php

namespace Delirehberi\ImportBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('delirehberi:import')
            ->addArgument('map',InputArgument::OPTIONAL,"")
            ->addArgument('entity',InputArgument::OPTIONAL,"")
            ->addOption('debug','-d',InputArgument::OPTIONAL,"Debug mode")
            ->addOption('offset','-o',InputArgument::OPTIONAL,"Offset")
            ->addOption('limit','-l',InputArgument::OPTIONAL,"Limit")
            ->setDescription('Import any data to your project');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $this->getApplication()->setAutoExit(false);
        $importManager = $container->get('delirehberi.import.manager');
        $output->writeln("Import Started");
        $debug = $input->getOption('debug')?true:false;
        $limit = $input->getOption('limit');
        $offset = $input->getOption('offset');
        $entity = $input->getArgument('entity');
        $map = $input->getArgument('map');
        $importManager
            ->setDebug($debug)
            ->setLimit($limit)
            ->setOffset($offset)
        ;
        $importManager->startImport($output,$map,$entity);
        $output->writeln("Import Completed");
    }
}
