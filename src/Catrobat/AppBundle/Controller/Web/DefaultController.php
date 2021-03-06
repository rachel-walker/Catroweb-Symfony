<?php

namespace Catrobat\AppBundle\Controller\Web;

use Catrobat\AppBundle\StatusCode;
use Catrobat\AppBundle\Entity\User;
use Doctrine\Common\Collections\Criteria;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Catrobat\AppBundle\Services\FeaturedImageRepository;
use Catrobat\AppBundle\Entity\FeaturedRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Intl\Intl;

class DefaultController extends Controller
{
  const MIN_PASSWORD_LENGTH = 6;
  const MAX_PASSWORD_LENGTH = 32;
  const MAX_UPLOAD_SIZE = 5242880; // 5*1024*1024
  const MAX_AVATAR_SIZE = 300;

  /**
   * @Route("/", name="index")
   * @Method({"GET"})
   */
  public function indexAction(Request $request)
  {
      /* @var $image_repository FeaturedImageRepository */
      $image_repository = $this->get('featuredimagerepository');
      /* @var $repository FeaturedRepository */
      $repository = $this->get('featuredrepository');

      $programs = $repository->getFeaturedPrograms($request->getSession()->get('flavor'), 5, 0);

      $featured = array();
      foreach ($programs as $program)
      {
          $info = array();
          if ($program->getProgram() !== null)
          {
              if ($request->get('flavor'))
              {
                  $info['url'] = $this->generateUrl('program', array('id' => $program->getProgram()->getId(), 'flavor' => $request->get('flavor')));
              }
              else
              {
                  $info['url'] = $this->generateUrl('catrobat_web_program', array('id' => $program->getProgram()->getId()));
              }
          }
          else 
          {
              $info['url'] = $program->getUrl();
          }
          $info['image'] = $image_repository->getWebPath($program->getId(), $program->getImageType());;
          $featured[] = $info;
      }
    return $this->get("templating")->renderResponse('::index.html.twig', array("featured" => $featured));
  }

  /**
   * @Route("/program/{id}", name="program", requirements={"id":"\d+"})
   * @Route("/details/{id}", name="catrobat_web_detail", requirements={"id":"\d+"})
   * @Method({"GET"})
   */
  public function programAction(Request $request, $id, $flavor  = "pocketcode")
  {
    /**
     * @var $user \Catrobat\AppBundle\Entity\User
     * @var $program \Catrobat\AppBundle\Entity\Program
     * @var $reported_program \Catrobat\AppBundle\Entity\ProgramInappropriateReport
     */

    $program = $this->get("programmanager")->find($id);
    $screenshot_repository = $this->get("screenshotrepository");
    $elapsed_time = $this->get("elapsedtime");

    if (!$program || !$program->isVisible()) {
      throw $this->createNotFoundException('Unable to find Project entity.');
    }

    $viewed = $request->getSession()->get('viewed', array());
    if(!in_array($program->getId(), $viewed)) {
      $this->get("programmanager")->increaseViews($program);
      $viewed[] = $program->getId();
      $request->getSession()->set('viewed', $viewed);
    }

    $program_details = array (
      "screenshotBig" => $screenshot_repository->getScreenshotWebPath($program->getId()),
      "downloadUrl" => $this->generateUrl('download', array('id' => $program->getId(), 'fname' => $program->getName())),
      "languageVersion" => $program->getLanguageVersion(),
      "downloads" => $program->getDownloads(),
      "views" => $program->getViews(),
      "filesize" => sprintf("%.2f", $program->getFilesize()/1048576),
      "age" => $elapsed_time->getElapsedTime($program->getUploadedAt()->getTimestamp())
    );

    $user = $this->getUser();
    $user_programs = null;
    if($user) {
      $user_programs = $user->getPrograms()->matching(Criteria::create()
        ->where(Criteria::expr()->eq("id", $program->getId())));
    }

    $isReportedByUser = false;
    $em = $this->getDoctrine()->getManager();
    $reported_program = $em->getRepository("\Catrobat\AppBundle\Entity\ProgramInappropriateReport")
      ->findOneBy(array("program" => $program->getId()));

    if($reported_program)
      $isReportedByUser = ($user == $reported_program->getReportingUser());

    return $this->get("templating")->renderResponse('::program.html.twig', array(
      "program" => $program,
      "program_details" => $program_details,
      "my_program" => count($user_programs) > 0 ? true : false,
      "already_reported" => $isReportedByUser
    ));
  }

  /**
   * @Route("/search/{q}", name="search", requirements={"q":".+"})
   * @Method({"GET"})
   */
  public function searchAction($q)
  {
    return $this->get("templating")->renderResponse('::search.html.twig', array("q" => $q));
  }

  /**
   * @Route("/search/", name="empty_search")
   * @Method({"GET"})
   */
  public function searchNothingAction()
  {
    return $this->get("templating")->renderResponse('::search.html.twig', array("q" => null));
  }

  /**
   * @Route("/profile/{id}", name="profile", requirements={"id":"\d+"}, defaults={"id" = 0})
   * @Method({"GET"})
   */
  public function profileAction(Request $request, $id)
  {
    $twig = '::profile.html.twig';
    $program_count = 0;

    if($id == 0) {
      $user = $this->getUser();
      $twig = '::myprofile.html.twig';
    }
    else {
      $user = $this->get("usermanager")->find($id);
      $program_count = count($this->get("programmanager")->getUserPrograms($id));
    }


    if (!$user)
      return $this->redirectToRoute('fos_user_security_login');

    \Locale::setDefault(substr($request->getLocale(),0,2));
    $country = Intl::getRegionBundle()->getCountryName(strtoupper($user->getCountry()));

    return $this->get("templating")->renderResponse($twig, array(
      "profile" => $user,
      "program_count" => $program_count,
      "minPassLength" => self::MIN_PASSWORD_LENGTH,
      "maxPassLength" => self::MAX_PASSWORD_LENGTH,
      "country" => $country
    ));
  }

  /**
   * @Route("/profileSave", name="profile_save")
   * @Method({"POST"})
   */
  public function profileSaveAction(Request $request)
  {
    /**
     * @var $user \Catrobat\AppBundle\Entity\User
     */
    $user = $this->getUser();
    if(!$user)
      return $this->redirectToRoute('fos_user_security_login');

    $newPassword = $request->request->get('newPassword');
    $repeatPassword = $request->request->get('repeatPassword');
    $firstMail = $request->request->get('firstEmail');
    $secondMail = $request->request->get('secondEmail');
    $country = $request->request->get('country');

    try {
      $this->validateUserPassword($newPassword, $repeatPassword);
      $this->validateEmail($firstMail);
      $this->validateEmail($secondMail);
      $this->validateCountryCode($country);
    } catch(\Exception $e) {
      return JsonResponse::create(array('statusCode' => $e->getMessage()));
    }

    if($firstMail === "" && $secondMail === "")
      return JsonResponse::create(array('statusCode' => StatusCode::USER_UPDATE_EMAIL_FAILED));

    if($this->checkEmailExists($firstMail))
      return JsonResponse::create(array('statusCode' => StatusCode::EMAIL_ALREADY_EXISTS, 'email' => 1));
    if($this->checkEmailExists($secondMail))
      return JsonResponse::create(array('statusCode' => StatusCode::EMAIL_ALREADY_EXISTS, 'email' => 2));

    if($newPassword !== "")
      $user->setPlainPassword($newPassword);
    if($firstMail !== "" && $firstMail != $user->getEmail())
      $user->setEmail($firstMail);
    if($firstMail !== "" && $secondMail !== "" && $secondMail != $user->getAdditionalEmail())
      $user->setAdditionalEmail($secondMail);
    if($firstMail !== "" && $secondMail === "")
      $user->setAdditionalEmail("");
    if($firstMail === "" && $secondMail === "" && $user->getAdditionalEmail() !== "") {
      $user->setEmail($user->getAdditionalEmail());
      $user->setAdditionalEmail("");
    }
    if($firstMail === "" && $secondMail !== "") {
      $user->setEmail($secondMail);
      $user->setAdditionalEmail("");
    }

    $user->setCountry($country);

    $this->get("usermanager")->updateUser($user);

    return JsonResponse::create(array('statusCode' => StatusCode::OK));
  }

  /**
   * @Route("/profileDeleteProgram/{id}", name="profile_delete_program", requirements={"id":"\d+"}, defaults={"id" = 0})
   * @Method({"GET"})
   */
  public function deleteProgramAction($id)
  {
    /**
     * @var $user \Catrobat\AppBundle\Entity\User
     * @var $program \Catrobat\AppBundle\Entity\Program
     */
    if($id == 0)
      return $this->redirectToRoute('profile');

    $user = $this->getUser();
    if(!$user)
      return $this->redirectToRoute('fos_user_security_login');

    $programs = $user->getPrograms()->matching(Criteria::create()
      ->where(Criteria::expr()->eq("id", $id)));

    $program = $programs[0];
    if(!$program)
      throw $this->createNotFoundException('Unable to find Project entity.');

    $program->setVisible(false);

    $em = $this->getDoctrine()->getManager();
    $em->persist($program);
    $em->flush();

    return $this->redirectToRoute('profile');
  }

  /**
   * @Route("/profileUploadAvatar", name="profile_upload_avatar")
   * @Method({"POST"})
   */
  public function uploadAvatarAction(Request $request)
  {
    /**
     * @var $user \Catrobat\AppBundle\Entity\User
     */
    $user = $this->getUser();
    if(!$user)
      return $this->redirectToRoute('fos_user_security_login');

    $image_base64 = $request->request->get('image');

    try {
      $image_base64 = $this->checkAndResizeBase64Image($image_base64);
    }
    catch(\Exception $e) {
      return JsonResponse::create(array('statusCode' => $e->getMessage()));
    }

    $user->setAvatar($image_base64);
    $this->get("usermanager")->updateUser($user);

    return JsonResponse::create(array(
      'statusCode' => StatusCode::OK,
      'image_base64' => $image_base64
    ));
  }

  /**
   * @Route("/termsOfUse", name="termsOfUse")
   * @Method({"GET"})
   */
  public function termsOfUseAction()
  {
    return $this->get("templating")->renderResponse('::termsOfUse.html.twig');
  }

  /**
   * @Route("/licenseToPlay", name="licenseToPlay")
   * @Method({"GET"})
   */
  public function licenseToPlayAction()
  {
    return $this->get("templating")->renderResponse('::licenseToPlay.html.twig');
  }


  //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
  //// private functions
  //////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  /*
   * @param string $username
   * @param int $pass1
   * @param int $pass2
   */
  private function validateUserPassword($pass1, $pass2)
  {
    if($pass1 !== $pass2)
      throw new \Exception(StatusCode::USER_PASSWORD_NOT_EQUAL_PASSWORD2);

    if(strcasecmp($this->getUser()->getUsername(), $pass1) == 0)
      throw new \Exception(StatusCode::USER_USERNAME_PASSWORD_EQUAL);

    if($pass1 != "" && strlen($pass1) < self::MIN_PASSWORD_LENGTH)
      throw new \Exception(StatusCode::USER_PASSWORD_TOO_SHORT);

    if($pass1 != "" && strlen($pass1) > self::MAX_PASSWORD_LENGTH)
      throw new \Exception(StatusCode::USER_PASSWORD_TOO_LONG);
  }

  /*
   * @param string $email
   */
  private function validateEmail($email)
  {
    $name = '[a-zA-Z0-9]((\.|\-|_)?[a-zA-Z0-9])*';
    $domain = '[a-zA-Z]((\.|\-)?[a-zA-Z0-9])*';
    $tld = '[a-zA-Z]{2,8}';
    $regEx = '/^('.$name.')@('.$domain.')\.('.$tld.')$/';

    if(!preg_match($regEx, $email) && !empty($email))
      throw new \Exception(StatusCode::USER_EMAIL_INVALID);
  }

  /*
   * @param string $country
   */
  private function validateCountryCode($country)
  {
    //todo: check if code is really from the drop-down
    if(!empty($country) && !preg_match('/[a-zA-Z]{2}/', $country))
      throw new \Exception(StatusCode::USER_COUNTRY_INVALID);
  }

  /*
   * @param string $email
   * @return bool
   */
  private function checkEmailExists($email)
  {
    if($email === "")
      return false;

    $userWithFirstMail = $this->get("usermanager")->findOneBy(array('email' => $email));
    $userWithSecondMail = $this->get("usermanager")->findOneBy(array('additional_email' => $email));

    if($userWithFirstMail != null && $userWithFirstMail != $this->getUser() || $userWithSecondMail != null && $userWithSecondMail != $this->getUser())
      return true;
    return false;
  }

  public function country($code, $locale = null)
  {
    $countries = Locale::getDisplayCountries($locale ?: $this->localeDetector->getLocale());
    if (array_key_exists($code, $countries)) {
      return $this->fixCharset($countries[$code]);
    }
    return '';
  }

  /**
   * @param string $image_base64
   * @throws \Exception
   * @return string
   */
  private function checkAndResizeBase64Image($image_base64)
  {
    $image_data = explode(";base64,",$image_base64);
    $data_regx = "/data:(.+)/";

    if(!preg_match($data_regx, $image_data[0]))
      throw new \Exception(StatusCode::UPLOAD_UNSUPPORTED_FILE_TYPE);

    $image_type = preg_replace("/data:(.+)/", "\\1", $image_data[0]);
    $image = null;

    switch($image_type) {
      case "image/jpg":
      case "image/jpeg":
        $image = imagecreatefromjpeg($image_base64);
        break;
      case "image/png":
        $image = imagecreatefrompng($image_base64);
        break;
      case "image/gif":
        $image = imagecreatefromgif($image_base64);
        break;
      default:
        throw new \Exception(StatusCode::UPLOAD_UNSUPPORTED_MIME_TYPE);
    }

    if(!$image)
      throw new \Exception(StatusCode::UPLOAD_UNSUPPORTED_FILE_TYPE);

    $image_data = preg_replace($data_regx, "\\2", $image_base64);
    $image_size = strlen(base64_decode($image_data));

    if($image_size > self::MAX_UPLOAD_SIZE)
      throw new \Exception(StatusCode::UPLOAD_EXCEEDING_FILESIZE);

    $width = imagesx($image);
    $height = imagesy($image);

    if($width == 0 || $height == 0)
      throw new \Exception(StatusCode::UPLOAD_UNSUPPORTED_FILE_TYPE);

    if(max($width, $height) > self::MAX_AVATAR_SIZE) {
      $new_image = imagecreatetruecolor(self::MAX_AVATAR_SIZE, self::MAX_AVATAR_SIZE);
      if(!$new_image)
        throw new \Exception(StatusCode::USER_AVATAR_UPLOAD_ERROR);

      imagesavealpha($new_image, true);
      imagefill($new_image, 0, 0, imagecolorallocatealpha($new_image, 0, 0, 0, 127));

      if(!imagecopyresized($new_image, $image, 0, 0, 0, 0, self::MAX_AVATAR_SIZE, self::MAX_AVATAR_SIZE, $width, $height)) {
        imagedestroy($new_image);
        throw new \Exception(StatusCode::USER_AVATAR_UPLOAD_ERROR);
      }

      ob_start();
      if(!imagepng($new_image)) {
        imagedestroy($new_image);
        throw new \Exception(StatusCode::USER_AVATAR_UPLOAD_ERROR);
      }

      $image_base64 = "data:image/png;base64," . base64_encode(ob_get_clean());
    }

    return $image_base64;
  }

}
