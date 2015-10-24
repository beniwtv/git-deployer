<?php
namespace GitDeployer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class HistoryCommand extends Command {
    
    protected function configure() {

        $this
            ->setName('history')
            ->setDescription('Display history information for a Git repository')
            ->addArgument(
                'repository',
                InputArgument::REQUIRED,
                'Which repository do you want to have the history for?'
            );

    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        
        $repository = $input->getArgument('repository');

        // -> Get logged-in service       
        $instance = \GitDeployer\AppInstance::getInstance();
        $appService = $instance->service();
        $appService->setInstances($input, $output, $this->getHelperSet());

        // -> Get currently known repositories
        $hasBeenFound = false;

        foreach ($appService->getProjects() as $key => $project) {
            if ($project->name() == $repository) {
                list($link, $history) = $appService->getHistory($project);

                // History information
                $output->writeln('<comment>History information about project</comment> <info>"' . $repository . '"</info>:' . "\n");
                $this->printHistory($history, $output);            

                $isRunning = true;

                while(strlen($link) > 0 && $isRunning) {
                    $helper = $this->getHelper('question');
                    $question = new ConfirmationQuestion('Continue to next page? (y/n)', false);

                    if (!$helper->ask($input, $output, $question)) {
                        $isRunning = false;
                    } else {
                        list($link, $history) = $appService->getHistory($project);
                        $this->printHistory($history, $output);
                    }
                }
               
                $hasBeenFound = true;
                break;
            }
        }
        
        if (!$hasBeenFound) {
            throw new \Exception('Project "' . $repository . '" could not be found! Please check your spelling!');       
        }

    }

    /**
     * Prints history like "git log" to the console
     * @param  array $history The history items to print
     */
    private function printHistory($history, $output) {

        foreach ($history as $item) {
            $output->writeln('<comment>commit</comment> <info>' . $item->commit() . '</info>');
            $output->writeln('Author: ' . $item->author() . ' <' . $item->authormail() . '>');
            $output->writeln('Date: ' . date('r', strtotime($item->date())));

            $formatter = $this->getHelper('formatter');
            $formattedBlock = $formatter->formatBlock(explode("\n", $item->message()), 'comment', true);
            $output->writeln($formattedBlock);
        }

    }

}
