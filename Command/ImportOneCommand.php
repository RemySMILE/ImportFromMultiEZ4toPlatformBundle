<?php

namespace Smile\ImportFromMultiEZ4toPlatformBundle\Command;

use Smile\ImportFromMultiEZ4toPlatformBundle\Helper\InitialImportHelper;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportOneCommand extends ContainerAwareCommand
{
    /** Super admin ID */
    const CREATOR_ID = 14;
    
    /** @var  InputInterface */
    protected $input;
    /** @var  OutputInterface */
    protected $output;

    /** @var  InitialImportHelper */
    protected $helper;

    protected function configure()
    {
        $this
            ->setName('smile:initial-import:import-one')
            ->setDescription('...')
            ->addArgument('site', InputArgument::OPTIONAL, 'Argument description')
            ->addArgument('object_id', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    function init(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getContainer()->get('smile.import_from_multi_ez4_to_platform.initial_import_helper');
        $helper->setInputOutputInterface($input, $output);
        $this->helper = $helper;
        $this->input = $input;
        $this->output = $output;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logAsSuperAdmin();
        $this->init($input, $output);

        $site = $input->getArgument('site');
        if (!$site) {
            throw new \Exception("Argument [site] obligatoire");
        }
        $object_id = $input->getArgument('object_id');
        if (!$object_id) {
            throw new \Exception("Argument [object_id] obligatoire");
        }
        $this->helper->setSite($site);
        $this->helper->checkIfContentExist(['object_id' => $object_id], true);
        
        $output->writeln('END');
    }
    
    /**
     * Se loger en tant que super admin.
     *
     * @throws \Exception
     */
    protected function logAsSuperAdmin()
    {
        $repository = $this->getContainer()->get('ezpublish.api.repository');
        $userID = self::CREATOR_ID;
        $user = $repository->getUserService()->loadUser($userID);
        if (!$user) {
            $msg = "Le user $userID n'existe pas.";
            throw new \Exception($msg);
        }
        $repository->setCurrentUser($user);
    }
}
