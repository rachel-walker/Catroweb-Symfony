<?php

namespace Catrobat\AppBundle\Controller\Api;

use Catrobat\AppBundle\Services\Formatter\ElapsedTimeStringFormatter;
use Catrobat\AppBundle\Entity\ProgramManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Catrobat\AppBundle\Services\ScreenshotRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

class RemixController extends Controller
{

  /**
   * @Route("/api/programs/getMostRemixed.json", name="api_get_most_remixed", defaults={"_format": "json"})
   * @Method({"GET"})
   */
  public function getMostRemixedProgramAction(Request $request)
  {

    $program_manager = $this->get("programmanager");

    $retArray = array ();
    $limit = intval($request->query->get('limit', 20));
    $offset = intval($request->query->get('offset', 0));
    $user_id = intval($request->query->get('user_id', 0));

    $programs = $program_manager->getMostRemixed($limit,$offset);

    $numbOfTotalProjects = count($program_manager->getMostRemixed(null,null));

    $retArray['CatrobatProjects'] = array ();
    foreach($programs as $program)
    {
      $new_program = array ();
      $new_program['ProjectId'] = $program->getId();
      $new_program['ProjectName'] = $program->getName();
      $new_program['ProjectNameShort'] = $program->getName();
      $new_program['Author'] = $program->getUser()->getUserName();
      $new_program['Description'] = $program->getDescription();
      $new_program['RemixOf'] = $program->getRemixOf() ? $program->getRemixOf()->getId() : null;
      $new_program['RemixCount'] = $program->getRemixCount();
      $new_program['ProjectUrl'] = ltrim($this->generateUrl('program', array('flavor' => $request->attributes->get("flavor"), 'id' => $program->getId())),"/");
      $new_program['DownloadUrl'] = ltrim($this->generateUrl('download', array('id' => $program->getId())),"/");
      $retArray['CatrobatProjects'][] = $new_program;
    }
    $retArray['completeTerm'] = "";
    $retArray['preHeaderMessages'] = "";

    $retArray['CatrobatInformation'] = array (
      "BaseUrl" => ($request->isSecure() ? 'https://' : 'http://'). $request->getHttpHost() . '/',
      "TotalProjects" => $numbOfTotalProjects,
      "ProjectsExtension" => ".catrobat"
    );

    return JsonResponse::create($retArray);
  }


  /**
   * @Route("/api/programs/getRemixOf.json", name="api_get_most_remixed", defaults={"_format": "json"})
   * @Method({"GET"})
   */
  public function getRemixOfAction(Request $request)
  {
    /**
     * @var $program \Catrobat\AppBundle\Entity\Program
     */

    $program_manager = $this->get("programmanager");

    $retArray = array ();
    $programId = intval($request->query->get('id', 0));
    $depth = intval($request->query->get('depth', 20));

    if($programId == 0)
    {
      return JsonResponse::create(array('Error' => 'Program id is missing.'));
    }

    $program = $program_manager->find($programId);
    if($program == null)
    {
      return JsonResponse::create(array('Error' => 'Program not found.'));
    }

    return JsonResponse::create(array (
        'id' => $programId,
        'childs' => $this->getChilds($program, $depth)
    ));
  }

  private function getChilds($program, $depth)
  {
    if($depth == 0)
      return null;

    $repo = $this->getDoctrine()->getManager()->getRepository('\Catrobat\AppBundle\Entity\Program');
    $childs = $repo->findBy(array('remix_of' => $program));

    $retArray = array();

    foreach($childs as $child)
    {
      $retArray[] = array(
        'id' => $child->getId(),
        'childs' => $child->getRemixCount() > 0 ? $this->getChilds($child, $depth-1) : null
      );
    }

    return $retArray;
  }
}
