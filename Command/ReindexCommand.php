<?php

namespace Oro\Bundle\SearchBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Update and reindex (automatically) fulltext-indexed table(s).
 * Use carefully on large datasets - do not run this task too often.
 *
 * @author magedan
 */
class ReindexCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('oro:search:reindex')
             ->setDescription('Update and reindex (automatically) fulltext-indexed table(s)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Starting reindex task');
        $output->writeln('');

        $doctrine = $this->getContainer()->get('doctrine');
        $orm      = $this->getContainer()->get('oro_search.search.engine');
        $em       = $doctrine->getManager();
        $itemRepo = $em->getRepository('OroSearchBundle:Item');

        $itemRepo->setDriversClasses($this->getContainer()->getParameter('oro_search.engine_orm'));

        $changed = $itemRepo->findBy(array(
            'changed' => true
        ));

        // probably, fulltext index should be dropped here for performance reasons
        // ...

        foreach ($changed as $item) {
            $output->write(sprintf('  Processing "%s" with id #%u', $item->getEntity(), $item->getRecordId()));

            $entity = $doctrine
                ->getRepository($item->getEntity())
                ->find($item->getRecordId());

            if ($entity) {
                $item->setChanged(false)
                     ->saveItemData($orm->mapObject($entity));
            } else {
                $em->remove($item);
            }
        }

        $em->flush();

        // recreate fulltext index, if necessary
        // ...

        $output->writeln('');
        $output->writeln(sprintf('Total indexed items: %u', count($changed)));
    }
}