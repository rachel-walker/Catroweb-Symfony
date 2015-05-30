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
//    $screenshot_repository = $this->get("screenshotrepository");
//    $elapsed_time = $this->get("elapsedtime");
    $flavor = $request->getSession()->get('flavor');

    $retArray = array ();
    $limit = intval($request->query->get('limit', 20));
    $offset = intval($request->query->get('offset', 0));
    $user_id = intval($request->query->get('user_id', 0));



//    if ($sortBy == "downloads")
//      $programs = $program_manager->getMostDownloadedPrograms($flavor, $limit, $offset);
//    else if ($sortBy == "views")
//      $programs = $program_manager->getMostViewedPrograms($flavor, $limit, $offset);
//    else if ($sortBy == "user")
//      $programs = $program_manager->getUserPrograms($user_id);
//    else
//      $programs = $program_manager->getRecentPrograms($flavor, $limit, $offset);

    $programs = $program_manager->getMostRemixed($limit,$offset);


//    if ($sortBy == "user")
    $numbOfTotalProjects = count($programs);
//    else
    //     $numbOfTotalProjects = $program_manager->getTotalPrograms($flavor);

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

}
