<?php
namespace Catrobat\AppBundle\Commands;

use Catrobat\AppBundle\Services\CatrobatFileExtractor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Catrobat\AppBundle\Entity\ProgramManager;
use Catrobat\AppBundle\Entity\UserManager;
use Symfony\Component\HttpFoundation\File\File;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Process\Process;
use Catrobat\AppBundle\Entity\Program;
use Catrobat\AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Catrobat\AppBundle\Entity\FeaturedProgram;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Helper\ProgressBar;

class ImportLegacyCommand extends ContainerAwareCommand
{
    const RESOURCE_CONTAINER_FILE = "resources.tar";
    const SQL_CONTAINER_FILE = "sql.tar";
    const SQL_WEB_CONTAINER_FILE = "catroweb-sql.tar.gz";
    const TSV_USERS_FILE = "2034.dat";
    const TSV_PROGRAMS_FILE = "2041.dat";
    const TSV_FEATURED_PROGRAMS = "2037.dat";

    private $fileystem;
    private $user_manager;
    private $program_manager;
    private $output;
    
    private $importdir;
    private $finder;
    private $filesystem;
    
    private $thumbnaildir;
    private $screenshotdir;
    private $screenshot_repository;
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
        $this->setName('catrobat:legacy:import')
            ->setDescription('Import a legacy backup')
            ->addArgument('backupfile', InputArgument::REQUIRED, 'legacy backup file (tar.gz)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->filesystem = new Filesystem();
        $this->finder = new Finder();
        $this->screenshot_repository = $this->getContainer()->get('screenshotrepository');
        $this->catrobat_file_repository = $this->getContainer()->get('filerepository');
        
        $this->executeSymfonyCommand("catrobat:purge", array("--force" => true), $output);
        
        $backup_file = $input->getArgument('backupfile');

        $this->importdir = $this->createTempDir();
        $this->writeln("Using Temp directory " . $this->importdir);
        
        $temp_dir = $this->importdir;
        $this->executeShellCommand("tar xfz $backup_file --directory $temp_dir", "Extracting backupfile");
        $this->executeShellCommand("tar xf $temp_dir/".self::SQL_CONTAINER_FILE." --directory $temp_dir", "Extracting SQL files");
        $this->executeShellCommand("tar xfz $temp_dir/".self::SQL_WEB_CONTAINER_FILE." --directory $temp_dir", "Extracting Catroweb SQL files");
        $this->executeShellCommand("tar xf $temp_dir/".self::RESOURCE_CONTAINER_FILE." --directory $temp_dir", "Extracting resource files");
        
        $this->importUsers($this->importdir."/".self::TSV_USERS_FILE);
        $this->importPrograms($this->importdir."/".self::TSV_PROGRAMS_FILE);
        $this->importProgramFiles($this->importdir."/".self::TSV_PROGRAMS_FILE);
        
        $row = 0;
        $features_tsv = $this->importdir."/".self::TSV_FEATURED_PROGRAMS;
        if (($handle = fopen($features_tsv, "r")) !== false) {
            while (($data = fgetcsv($handle, 0, "\t")) !== false) {
                $num = count($data);
                if ($num > 2) {
                    $program = new FeaturedProgram();
                    $program->setProgram($this->program_manager->find($data[1]));
                    $program->setActive($data[3] === "t");
                    $program->setNewFeaturedImage(new File($this->importdir."/resources/featured/".$data[1].".jpg"));
                    $this->em->persist($program);
                } else {
                    break;
                }
                $row ++;
            }
            $this->em->flush();
            fclose($handle);
            $this->writeln("Imported ".$row." featured programs");
        }

        $this->filesystem->remove($temp_dir);
    }

    protected function importPrograms($program_file)
    {
        $row = 0;
        $skipped = 0;

        $progress = new ProgressBar($this->output);
        $progress->setFormat(' %current%/%max% [%bar%] %message%');
        $progress->start();
        
        $metadata = $this->em->getClassMetaData("Catrobat\AppBundle\Entity\Program");
        $metadata->setIdGeneratorType(\Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_NONE);

        if (($handle = fopen($program_file, "r")) !== false) {
            while (($data = fgetcsv($handle, 0, "\t")) !== false) {
                $num = count($data);
                if ($num > 2) {
                    $id = $data[0];
                    $language_version = $data[13];
                    
                    $progress->setMessage($data[1] . " (" . $id . ")");
                    $progress->advance();
                    
                    // ignore old programs except for manually changed ones - because FU
                    if (version_compare($language_version, "0.8", "<") && $id != 821)
                    {
                        $progress->clear();
                        $this->writeln("<error>Could not import program " . $id . " - version too old: " .$language_version. "</error>");
                        $progress->display();
                        $skipped++;
                        continue;
                    }
                    $program = new Program();
                    $program->setId($id);
                    $program->setName($data[1]);
                    $description = $data[2];
                    $description = str_replace("<br />\\n","\n", $description);
                    $program->setDescription($description);
                    $program->setUploadedAt(new \DateTime($data[4], new \DateTimeZone('UTC')));
                    $program->setUploadIp($data[5]);
                    $program->setDownloads($data[6]);
                    $program->setViews($data[7]);
                    $program->setVisible($data[8] === "t");
                    $program->setUser($this->user_manager->find($data[9]));
                    $program->setUploadLanguage($data[10]);
                    $program->setFilesize($data[11]);
                    $program->setCatrobatVersionName($data[12]);

                    if ($id == 821)
                    {
                        $program->setLanguageVersion("0.8");
                    }
                    {
                        $program->setLanguageVersion($language_version);
                    }
                    
                    $program->setRemixCount($data[19]);
                    $program->setApproved($data[20] === "t");
                    $program->setCatrobatVersion(1);
                    $program->setFlavor("pocketcode");
                    $this->em->persist($program);
                } else {
                    break;
                }
                $row ++;
            }
            fclose($handle);
            
            $progress->setMessage("Saving to database");
            $progress->advance();
            $this->em->flush();
            $progress->setMessage("");
            $progress->finish();
            $this->writeln("");
            $this->writeln("<info>Imported ".$row." programs (Skipped " . $skipped . ")</info>");
        }
    }

    protected function importProgramFiles($program_file)
    {
        $row = 0;
        $skipped = 0;
    
        $progress = new ProgressBar($this->output);
        $progress->setFormat(' %current%/%max% [%bar%] %message%');
        $progress->start();
    
        $metadata = $this->em->getClassMetaData("Catrobat\AppBundle\Entity\Program");
        $metadata->setIdGeneratorType(\Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_NONE);
    
        if (($handle = fopen($program_file, "r")) !== false) {
            while (($data = fgetcsv($handle, 0, "\t")) !== false) {
                $num = count($data);
                if ($num > 2) {
                    $id = $data[0];
                    $language_version = $data[13];
    
                    $progress->setMessage($data[1] . " (" . $id . ")");
                    $progress->advance();
    
                    if (version_compare($language_version, "0.8", "<") && $id != 821)
                    {
                        $skipped++;
                        continue;
                    }
                    $this->importScreenshots($id);
                    $this->importProgramfile($id);
                } else {
                    break;
                }
                $row ++;
            }
            fclose($handle);
    
            $progress->setMessage("Saving to database");
            $progress->advance();
            $this->em->flush();
            $progress->setMessage("");
            $progress->finish();
            $this->writeln("");
            $this->writeln("<info>Imported ".$row." programs (Skipped " . $skipped . ")</info>");
        }
    }
    
    protected function importUsers($user_file)
    {
        print_r($user_file);

        $row = 0;

        $progress = new ProgressBar($this->output);
        $progress->setFormat(' %current%/%max% [%bar%] %message%');
        $progress->start();
        
        $metadata = $this->em->getClassMetaData("Catrobat\AppBundle\Entity\User");
        $metadata->setIdGeneratorType(\Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_NONE);

        if (($handle = fopen($user_file, "r")) !== false) {
            while (($data = fgetcsv($handle, 0, "\t")) !== false) {
                $num = count($data);
                if ($num > 2) {
                    // Special case - same email on two accounts, this one has no programs
                    if ($data[1] == "paul70078") {
                        continue;
                    }
                    // Special case - no id 0
                    if ($data[0] == 0) {
                        continue;
                    }

                    $progress->setMessage($data[1] . " (" . $data[0]. ")");
                    $progress->advance();
                    
                    $user = new User();
                    $user->setId($data[0]);
                    $user->setUsername($data[1]);
                    $user->setPassword($data[2]);
                    $user->setEmail($data[3]);
                    $user->setCountry(strtoupper($data[4]));
                    $user->setUploadToken($data[11]);
                    $user->setEnabled(true);
                    $user->setAvatar(($data[13] === "\N") ? null : $data[13]);
                    $user->setAdditionalEmail($data[14] === "\N" ? null : $data[14]);
                    $this->em->persist($user);
                } else {
                    break;
                }
                $row ++;
            }
            fclose($handle);
            $progress->setMessage("Saving to database");
            $progress->advance();
            $this->em->flush();
            $progress->setMessage("");
            $progress->finish();
            $this->writeln("");
            $this->writeln("<info>Imported ".$row." users</info>");
        }
    }

    private function importScreenshots($id)
    {
        $screenhot_dir = $this->importdir . "/resources/thumbnails/";
        $screenshot_path = $screenhot_dir .  $id . "_large.png";
        $thumbnail_path = $screenhot_dir .  $id . "_small.png";
        if (file_exists($screenshot_path))
        {
            $this->screenshot_repository->importProgramAssets($screenshot_path, $thumbnail_path, $id);
        }
    }
    
    private function importProgramfile($id)
    {
        $filepath = $this->importdir . "/resources/projects/" . "$id" . ".catrobat";

        if (file_exists($filepath))
        {
            /* @var $fileextractor CatrobatFileExtractor*/
            $fileextractor = $this->getContainer()->get('fileextractor');
            $router = $this->getContainer()->get('router');
            $extractedfile = $fileextractor->extract(new File($filepath));
            $xmlprops = $extractedfile->getProgramXmlProperties();

            $xmlprops->header->url = $router->generate('program', array('id' => $id));

            $matches = array();
            preg_match("/([\d]+)$/", $xmlprops->header->remixOf->__toString(),$matches);
            if (isset($matches[1]))
            {
                $remix_program_id = intval($matches[1]);
                if($remix_program_id != "")
                {
                    $xmlprops->header->remixOf = $router->generate('program', array('id' => $remix_program_id));
                    $parent = $this->program_manager->find($remix_program_id);
                    if($parent != null) {
                      $program = $this->program_manager->find($id);
                      $program->setRemixOf($parent);
                    }
                    else 
                    {
                        $this->writeln("Could not set remix info: program not in database (" . $remix_program_id . ")");
                    }
                }
            }
            $this->catrobat_file_repository->saveProgramfile(new File($filepath), $id);
        }
    }
    
    private function executeSymfonyCommand($command, $args, $output)
    {
        $command = $this->getApplication()->find($command);
        $args["command"] = $command;
        $input = new ArrayInput($args);
        $command->run($input, $output);
    }
    
    private function executeShellCommand($command, $description)
    {
        $this->write($description." ('".$command."') ... ");
        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();
        if ($process->isSuccessful()) {
            $this->writeln("OK");
    
            return true;
        } else {
            $this->writeln("failed!");
    
            return false;
        }
    }
    
    private function write($string)
    {
        if ($this->output != null) {
            $this->output->write($string);
        }
    }

    private function writeln($string)
    {
        if ($this->output != null) {
            $this->output->writeln($string);
        }
    }

    private function createTempDir()
    {
        $tempfile = tempnam(sys_get_temp_dir(), 'catimport');
        if (file_exists($tempfile)) {
            unlink($tempfile);
        }
        mkdir($tempfile);
        if (is_dir($tempfile)) {
            return $tempfile;
        }
    }
}
