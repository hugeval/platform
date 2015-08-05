<?php

namespace Oro\Bundle\ImapBundle\Migrations\Data\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;

use Oro\Bundle\ImapBundle\Entity\UserEmailOrigin;

class UpdateOriginName extends AbstractFixture
{
    /**
     * {@inheritDoc}
     */
    public function load(ObjectManager $manager)
    {
        $repo = $manager->getRepository('OroImapBundle:UserEmailOrigin');

        $origins = $repo->findAll();
        if ($origins) {
            foreach ($origins as $origin) {
                $origin->setMailboxName($origin->getUser());
            }

            $manager->flush();
        }
    }
}
