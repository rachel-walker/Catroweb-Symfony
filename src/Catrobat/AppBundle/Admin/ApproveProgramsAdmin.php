<?php
namespace Catrobat\AppBundle\Admin;

use Catrobat\AppBundle\Services\CatrobatFileExtractor;
use Catrobat\AppBundle\Services\ProgramFileRepository;
use Sonata\AdminBundle\Admin\Admin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Catrobat\AppBundle\Entity\User;
use Catrobat\AppBundle\Entity\Project;
use Sonata\AdminBundle\Route\RouteCollection;


class ApproveProgramsAdmin extends Admin
{

    public function createQuery($context = 'list')
    {
        $query = parent::createQuery($context);
        $query->andWhere(
            $query->expr()->eq($query->getRootAlias() . '.approved', ':approved_filter')
        );
        $query->setParameter('approved_filter', 'true');
        return $query;
    }

    protected function configureShowFields(ShowMapper $showMapper)
    {
      // Here we set the fields of the ShowMapper variable, $showMapper (but this can be called anything)
      $showMapper

          /*
           * The default option is to just display the value as text (for boolean this will be 1 or 0)
           */
          ->add('Thumbnail', null, array('template' => ':Admin:program_thumbnail_image.html.twig'))
          ->add('id')
          ->add('Name')
          ->add('Description')
          ->add('version')
          ->add('user', 'entity', array('class' => 'Catrobat\AppBundle\Entity\User'))
          ->add('upload_ip')
          ->add('filename')
          ->add('visible','boolean')
          ->add('Images', null, array('template' => ':Admin:program_containing_image.html.twig'))
          ->add('Sounds', null, array('template' => ':Admin:program_containing_sound.html.twig'))
      ;

    }

    public function preUpdate($program)
    {
        $old_program = $this->getModelManager()->getEntityManager($this->getClass())->getUnitOfWork()->getOriginalEntityData($program);

        if($old_program["approved"] == false && $program->getApproved() == true)
        {
            $program->setApprovedByUser($this->getConfigurationPool()->getContainer()->get('security.context')->getToken()->getUser());
            $this->getModelManager()->update($program);
        }elseif($old_program["approved"] == true && $program->getApproved() == false)
        {
            $program->setApprovedByUser(null);
            $this->getModelManager()->update($program);
        }
    }

    // Fields to be shown on create/edit forms
    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper
            ->add('name', 'text', array('label' => 'Program name'))
            ->add('user', 'entity', array('class' => 'Catrobat\AppBundle\Entity\User'))
        ;
    }

    // Fields to be shown on filter forms
    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('name')
            ->add('user')
        ;
    }

    // Fields to be shown on lists
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('id')
            ->add('user')
            ->add('name')
            ->add('description')
            ->add('approved', 'boolean', array('editable' => true))
            ->add('_action', 'actions', array('actions' => array('show' => array(),)))
        ;
    }


    public function getThumbnailImageUrl($object)
    {
      return "/".$this->getConfigurationPool()->getContainer()->get("screenshotrepository")->getThumbnailWebPath($object->getId());
    }


    public function getContainingImageUrls($object)
    {

      /* @var $fileEctractor CatrobatFileExtractor */
      /* @var $fileRepo ProgramFileRepository */

      $fileExctractor = $this->getConfigurationPool()->getContainer()->get("fileextractor");
      $fileRepo = $this->getConfigurationPool()->getContainer()->get("filerepository");
      $extractedFile = $fileExctractor->extract($fileRepo->getProgramFile($object->getId()));
      //TODO: Cleanup, directory will never get used again

      return $extractedFile->getContainingImagePaths();
    }


  public function getContainingSoundUrls($object)
  {

    /* @var $fileEctractor CatrobatFileExtractor */
    /* @var $fileRepo ProgramFileRepository */

    $fileExctractor = $this->getConfigurationPool()->getContainer()->get("fileextractor");
    $fileRepo = $this->getConfigurationPool()->getContainer()->get("filerepository");
    $extractedFile = $fileExctractor->extract($fileRepo->getProgramFile($object->getId()));
    //TODO: Cleanup, directory will never get used again

    return count($extractedFile->getContainingSoundPaths())>0?$extractedFile->getContainingSoundPaths():null;
  }


  protected function configureRoutes(RouteCollection $collection)
    {
        $collection->remove('create')->remove('delete')->remove('edit');
    }
}

