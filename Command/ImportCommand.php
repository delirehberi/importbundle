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
            ->addArgument('map',InputArgument::OPTIONAL,"Map specified import")
            ->addOption('debug','-d',InputArgument::OPTIONAL,"Debug mode")
            ->setDescription('Import any data to your project');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $importManager = $container->get('delirehberi.import.manager');
        $output->writeln("Import Started");
        $importManager->setDebug($input->getOption('debug')?true:false);
        $importManager->startImport($output,$input->getArgument('map'));
        $output->writeln("Import Completed");
    }
}
