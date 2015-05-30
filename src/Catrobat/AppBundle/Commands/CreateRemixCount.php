<?php
namespace Catrobat\AppBundle\Commands;

use Catrobat\AppBundle\Services\CatrobatFileExtractor;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Catrobat\AppBundle\Entity\ProgramManager;
use Catrobat\AppBundle\Entity\UserManager;
use Doctrine\ORM\EntityManager;
use Catrobat\AppBundle\Entity\Program;
use Catrobat\AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Catrobat\AppBundle\Entity\FeaturedProgram;

class CreateRemixCount extends ContainerAwareCommand
{
    private $user_manager;
    private $program_manager;
    private $output;
    
    private $importdir;
    private $finder;
    private $filesystem;

    private $catrobat_file_repository;
    
    public function __construct(Filesystem $filesystem, UserManager $user_manager, ProgramManager $program_manager, EntityManager $em)
    {
        parent::__construct();
        $this->fileystem = $filesystem;
        $this->user_manager = $user_manager;
        $this->program_manager = $program_manager;
        $this->em = $em;
    }

    protected function configure()
    {
        $this->setName('catrobat:remix:count')
            ->setDescription('Count the remixes and update the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->filesystem = new Filesystem();
        $this->finder = new Finder();
        $this->catrobat_file_repository = $this->getContainer()->get('filerepository');

        $output->writeln('Setting all counts to 0 ...');
        $this->resetCounts();

        $output->writeln('Start counting...');
        $this->countRemixes();

    }

    protected function resetCounts()
    {
        /**
         * @var $program \Catrobat\AppBundle\Entity\Program
         */
        $programs = $this->program_manager->findAll();

        foreach($programs as $program)
        {
            $program->setRemixCount(0);
            $this->em->persist($program);
        }
        $this->em->flush();
    }

    protected function countRemixes()
    {
        /**
         * @var $program \Catrobat\AppBundle\Entity\Program
         * @var $program_up \Catrobat\AppBundle\Entity\Program
         */
        $programs = $this->program_manager->findAll();

        foreach($programs as $program)
        {
            if ($program->getRemixOf() != null)
            {
                $this->output->writeln('Program with remix info found ...');

                $program_up = $this->program_manager->find($program->getRemixOf()->getId());

                if($program->getId() == $program_up->getId())
                {
                    $program_up->setRemixOf(null);
                }
                else if ($program_up != null)
                {
                    $program_up->setRemixCount($program_up->getRemixCount() + 1);
                }
                $this->em->persist($program_up);
            }
        }
        $this->output->writeln('Finished ... saving database');
        $this->em->flush();
    }
}
