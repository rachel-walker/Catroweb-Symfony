<?php

namespace Catrobat\AppBundle\Controller\Api;

use Catrobat\AppBundle\Events\ReportInsertEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator;
use Catrobat\AppBundle\Entity\UserManager;
use Catrobat\AppBundle\Entity\ProgramManager;
use Catrobat\AppBundle\Services\TokenGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Catrobat\AppBundle\StatusCode;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Catrobat\AppBundle\Entity\ProgramInappropriateReport;

class ReportController extends Controller
{
  /**
   * @Route("/api/reportProgram/reportProgram.json", name="catrobat_api_report_program", defaults={"_format": "json"})
   * @Method({"POST","GET"})
   */
  public function reportProgramAction(Request $request)
  {
    /* @var $context \Symfony\Component\Security\Core\SecurityContext */
    /* @var $programmanager \Catrobat\AppBundle\Entity\ProgramManager */
    /* @var $program \Catrobat\AppBundle\Entity\Program */

    $context = $this->get("security.context");
    $programmanager = $this->get("programmanager");
    $entityManager = $this->getDoctrine()->getManager();
    $eventdispacher = $this->get("event_dispatcher");

    $response = array();
    if(!$request->get('program') || !$request->get('note'))
    {
      $response["statusCode"] = StatusCode::MISSING_POST_DATA;
      $response["answer"] = $this->trans("errors.post-data");
      $response["preHeaderMessages"] = "";
      return JsonResponse::create($response);
    }

    $program = $programmanager->find($request->get('program'));
    if($program == null)
    {
      $response["statusCode"] = StatusCode::INVALID_PROGRAM;
      $response["answer"] = $this->trans("errors.program.invalid");
      $response["preHeaderMessages"] = "";
      return JsonResponse::create($response);
    }

    $report = new ProgramInappropriateReport();

    if($context->isGranted("IS_AUTHENTICATED_REMEMBERED"))
    {
      $report->setReportingUser($context->getToken()->getUser()); //could be anon
    }
    else
    {
      $report->setReportingUser(NULL); //could be anon
    }

    $program->setVisible(false);
    $report->setNote($request->get('note'));
    $report->setProgram($program);

    $entityManager->persist($report);
    $entityManager->flush();

    $eventdispacher->dispatch("catrobat.report.insert", new ReportInsertEvent($request->get('note'),$report));

    $response = array();
    $response["answer"] = $this->trans("success.report");
    $response["statusCode"] = StatusCode::OK;

    return JsonResponse::create($response);
  }

  private function trans($message, $parameters = array())
  {
    return  $this->get("translator")->trans($message,$parameters,"catroweb");
  }
}
