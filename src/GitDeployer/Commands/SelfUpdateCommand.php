<?php
namespace GitDeployer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Humbug\SelfUpdate\Updater;

class SelfUpdateCommand extends Command {
    
    protected function configure() {

        $this
            ->setName('self-update')
            ->setDescription('Updates Git-Deployer to the latest version');

    }

    protected function execute(InputInterface $input, OutputInterface $output) {

        $updater = new Updater();
        $updater->setStrategy(Updater::STRATEGY_GITHUB);
        $updater->getStrategy()->setPackageName('relamptk/git-deployer');
        $updater->getStrategy()->setPharName('git-deployer.phar');
        $updater->getStrategy()->setCurrentLocalVersion($this->getApplication()->getVersion());

        $result = $updater->update();

        if ($result) { 
            $new = $updater->getNewVersion();
            $output->writeln('<info>Git-Deployer</info> updated to version '. $new . '.');
        } else {
            $output->writeln('<info>Git-Deployer</info> is already on the latest version!');
        }

    }

}
