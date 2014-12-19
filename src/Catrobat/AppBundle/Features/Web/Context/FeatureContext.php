<?php

namespace Catrobat\AppBundle\Features\Web\Context;

use Behat\Behat\Context\CustomSnippetAcceptingContext;
use Behat\MinkExtension\ServiceContainer\MinkExtension;
use Catrobat\AppBundle\Entity\Program;
use Catrobat\AppBundle\Entity\StarterCategory;
use Symfony\Component\HttpKernel\KernelInterface;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Mink\Exception\Exception;
use Behat\Gherkin\Node\PyStringNode, Behat\Gherkin\Node\TableNode;
use Behat\Behat\Hook\Scope\AfterStepScope;
use WebDriver;

require_once 'PHPUnit/Framework/Assert/Functions.php';

//
// Require 3rd-party libraries here:
//
//   require_once 'PHPUnit/Autoload.php';
//   require_once 'PHPUnit/Framework/Assert/Functions.php';
//

/**
 * Feature context.
 */
class FeatureContext extends MinkContext implements KernelAwareContext, CustomSnippetAcceptingContext
{
  private $kernel;
  private $screenshot_directory;

  const BASE_URL = 'http://catroid.local/app_test.php/';
  const AVATAR_DIR = "./testdata/DataFixtures/AvatarImages/";

  /**
   * Initializes context with parameters from behat.yml.
   *
   * @param array $screenshot_directory
   * @throws \Exception
   */
  public function __construct($screenshot_directory)
  {
    $this->screenshot_directory = preg_replace('/([^\/]+)$/', '$1/', $screenshot_directory);
    if (!is_dir($this->screenshot_directory))
    {
      throw new \Exception("No screenshot directory specified!");
    }
  }

  
  /**
   * Sets HttpKernel instance.
   * This method will be automatically called by Symfony2Extension ContextInitializer.
   *
   * @param KernelInterface $kernel
   */
  public function setKernel(KernelInterface $kernel)
  {
      $this->kernel = $kernel;
  }

  public static function getAcceptedSnippetType()
  {
    return 'regex';
  }

  private function deleteScreens()
  {
    $files = glob($this->screenshot_directory.'*');
    foreach($files as $file) {
      if(is_file($file))
        unlink($file);
    }
  }

  /**
   * @When /^I go to the website root$/
   */
  public function iGoToTheWebsiteRoot()
  {
    $this->getSession()->visit(self::BASE_URL);
  }

  /**
   * @BeforeScenario
   */
  public function setup()
  {
    $this->getSession()->resizeWindow(1280, 1000);
  }

  /**
   * @AfterScenario
   */
  public function resetSession()
  {
      $this->getSession()->getDriver()->reset();
  }
  
  /**
   * @AfterStep
   */
  public function makeScreenshot(AfterStepScope $scope)
  {
      if (!$scope->getTestResult()->isPassed())
      {
         $this->saveScreenshot(null, $this->screenshot_directory);
      }
  }

  /**
   * @BeforeScenario @Mobile
   */
  public function resizeWindowMobile()
  {
    $this->getSession()->resizeWindow(320, 1000);
  }

  /**
   * @BeforeScenario @Tablet
   */
  public function resizeWindowTablet()
  {
    $this->getSession()->resizeWindow(768, 1000);
  }

  /**
   * @Then /^I should see the featured slider$/
   */
  public function iShouldSeeTheFeaturedSlider()
  {
    $this->assertSession()->responseContains("featured");
    assertTrue($this->getSession()->getPage()->findById('featuredPrograms')->isVisible());
  }

  /**
   * @Then /^I should see ([^"]*) programs$/
   */
  public function iShouldSeePrograms($arg1)
  {
    $arg1 = trim($arg1);

    switch($arg1) {
      case "some":
        $this->assertSession()->elementExists("css", ".program");
        break;

      case "no":
        $this->assertSession()->elementNotExists("css", ".program");
        break;

      case "newest":
        $this->assertSession()->elementExists("css", "#newest");
        $this->assertSession()->elementExists("css", ".program");
        break;

      case "most downloaded":
        $this->assertSession()->elementExists("css", "#mostDownloaded");
        $this->assertSession()->elementExists("css", ".program");
        break;

      case "most viewed":
        $this->assertSession()->elementExists("css", "#mostViewed");
        $this->assertSession()->elementExists("css", ".program");
        break;

      default:
        assertTrue(false);
    }
  }

  /**
   * @Then /^the selected language should be "([^"]*)"$/
   */
  public function theSelectedLanguageShouldBe($arg1)
  {
    switch($arg1) {
      case "English":
        $cookie = $this->getSession()->getCookie("hl");
        if(!empty($cookie))
          $this->assertSession()->cookieEquals("hl", "en");
        break;

      case "Deutsch":
        $this->assertSession()->cookieEquals("hl", "de");
        break;

      default:
        assertTrue(false);
    }
  }

  /**
   * @Then /^I switch the language to "([^"]*)"$/
   */
  public function iSwitchTheLanguageTo($arg1)
  {
    switch($arg1) {
      case "English":
        $this->getSession()->setCookie("hl", "en");
        break;
      case "Deutsch":
        $this->getSession()->setCookie("hl", "de");
        break;
      default:
        assertTrue(false);
    }
    $this->reload();
  }

  /**
   * @Then /^I should see a( [^"]*)? help image "([^"]*)"$/
   */
  public function iShouldSeeAHelpImage($arg1, $arg2)
  {
    $arg1 = trim($arg1);

    $this->assertSession()->responseContains("help-desktop");
    $this->assertSession()->responseContains("help-mobile");

    if($arg1 == "big") {
      assertTrue($this->getSession()->getPage()->find("css",".help-desktop")->isVisible());
      assertFalse($this->getSession()->getPage()->find("css",".help-mobile")->isVisible());
    }
    else if($arg1 == "small") {
      assertFalse($this->getSession()->getPage()->find("css",".help-desktop")->isVisible());
      assertTrue($this->getSession()->getPage()->find("css",".help-mobile")->isVisible());
    }
    else if($arg1 == "")
      assertTrue($this->getSession()->getPage()->find("css",".help-split-desktop")->isVisible());
    else
      assertTrue(false);

    $img = null;
    $path = null;

    switch($arg2) {
      case "Hour of Code":
        if($arg1 == "big") {
          $img = $this->getSession()->getPage()->findById("hour-of-code-desktop");
          $path = "/images/help/hour_of_code.png";
        }
        else if($arg1 == "small") {
          $img = $this->getSession()->getPage()->findById("hour-of-code-mobile");
          $path = "/images/help/hour_of_code_mobile.png";
        }
        else
          assertTrue(false);
        break;
      case "Step By Step":
        if($arg1 == "big") {
          $img = $this->getSession()->getPage()->findById("step-by-step-desktop");
          $path = "/images/help/step_by_step.png";
        }
        else if($arg1 == "small") {
          $img = $this->getSession()->getPage()->findById("step-by-step-mobile");
          $path = "/images/help/step_by_step_mobile.png";
        }
        else
          assertTrue(false);
        break;
      case "Tutorials":
        $img = $this->getSession()->getPage()->findById("tutorials");
        $path = "/images/help/tutorials.png";
        break;
      case "Starters":
        $img = $this->getSession()->getPage()->findById("starters");
        $path = "/images/help/starters.png";
        break;
      case "Discussion":
        if($arg1 == "big") {
          $img = $this->getSession()->getPage()->findById("discuss-desktop");
          $path = "/images/help/discuss.png";
        }
        else if($arg1 == "small") {
          $img = $this->getSession()->getPage()->findById("discuss-mobile");
          $path = "/images/help/discuss_mobile.png";
        }
        else
          assertTrue(false);
        break;
      default:
        assertTrue(false);
        break;

    }

    if($img != null) {
      assertEquals($img->getTagName(), "img");
      assertEquals($img->getAttribute("src"), $path);
      assertTrue($img->isVisible());
    }
    else
      assertTrue(false);

  }

  /**
   * @Given /^there are users:$/
   */
  public function thereAreUsers(TableNode $table)
  {
    /**
     * @var $user_manager \Catrobat\AppBundle\Entity\UserManager
     * @var $user \Catrobat\AppBundle\Entity\User
     */
    $user_manager = $this->kernel->getContainer()->get('usermanager');
    $users = $table->getHash();
    $user = null;
    for($i = 0; $i < count($users); $i ++)
    {
      $user = $user_manager->createUser();
      $user->setUsername($users[$i]["name"]);
      $user->setEmail("dev" . $i . "@pocketcode.org");
      $user->setAdditionalEmail("");
      $user->setPlainPassword($users[$i]["password"]);
      $user->setEnabled(true);
      $user->setUploadToken($users[$i]["token"]);
      $user->setCountry("at");
      $user_manager->updateUser($user, false);
    }
    $user_manager->updateUser($user, true);
  }

  /**
   * @Given /^there are programs:$/
   */
  public function thereArePrograms(TableNode $table)
  {
    /**
     * @var $program \Catrobat\AppBundle\Entity\Program
     */
    $em = $this->kernel->getContainer()->get('doctrine')->getManager();
    $programs = $table->getHash();
    for($i = 0; $i < count($programs); $i ++)
    {
      $user = $em->getRepository('AppBundle:User')->findOneBy(array (
        'username' => $programs[$i]['owned by']
      ));
      $program = new Program();
      $program->setUser($user);
      $program->setName($programs[$i]['name']);
      $program->setDescription($programs[$i]['description']);
      $program->setViews($programs[$i]['views']);
      $program->setDownloads($programs[$i]['downloads']);
      $program->setUploadedAt(new \DateTime($programs[$i]['upload time'], new \DateTimeZone('UTC')));
      $program->setCatrobatVersion(1);
      $program->setCatrobatVersionName($programs[$i]['version']);
      $program->setLanguageVersion(1);
      $program->setUploadIp("127.0.0.1");
      $program->setRemixCount(0);
      $program->setFilesize(0);
      $program->setVisible(isset($programs[$i]['visible']) ? $programs[$i]['visible']=="true" : true);
      $program->setUploadLanguage("en");
      $program->setApproved(false);
      $em->persist($program);
    }
    $em->flush();
  }

  /**
   * @When /^I click "([^"]*)"$/
   */
  public function iClick($arg1)
  {
    $arg1 = trim($arg1);

    $this->assertSession()->elementExists("css", $arg1);

    $this
      ->getSession()
      ->getPage()
      ->find("css", $arg1)
      ->click();
  }

  /**
   * @Then /^I should be logged ([^"]*)?$/
   */
  public function iShouldBeLoggedIn($arg1)
  {
    if($arg1 == "in") {
      $this->assertPageNotContainsText("Your password or username was incorrect.");
      $this->assertElementOnPage("#logo");
      $this->assertElementNotOnPage("#btn-login");
      $this->assertElementOnPage("#nav-dropdown");
      $this->getSession()->getPage()->find("css", ".show-nav-dropdown")->click();
      $this->assertElementOnPage("#nav-dropdown");
    }
    if($arg1 == "out") {
      $this->assertElementOnPage("#btn-login");
      $this->assertElementNotOnPage("#nav-dropdown");
    }
  }

  /**
   * @Given /^I( [^"]*)? log in as "([^"]*)" with the password "([^"]*)"$/
   */
  public function iAmLoggedInAsAsWithThePassword($arg1, $arg2, $arg3)
  {
    $this->visitPath("/pocketcode/login");
    $this->fillField("username", $arg2);
    $this->fillField("password", $arg3);
    $this->pressButton("Login");
    if($arg1 == "try to")
      $this->assertPageNotContainsText("Your password or username was incorrect.");
  }

  /**
   * @Given /^I wait for the server response$/
   */
  public function iWaitForTheServerResponse()
  {
    $this->getSession()->wait(5000, '(0 === jQuery.active)');
  }

  /**
   * @Given /^I make a screenshot$/
   */
  public function iMakeAScreenshot()
  {
    $this->makeScreenshot();
  }

  /**
   * @Then /^"([^"]*)" must be selected in "([^"]*)"$/
   */
  public function mustBeSelectedIn($country, $select)
  {
    $field = $this->getSession()->getPage()->findField($select);
    assertTrue($country == $field->getValue());
  }

  /**
   * @When /^(?:|I )attach the avatar "(?P<path>[^"]*)" to "(?P<field>(?:[^"]|\\")*)"$/
   */
  public function attachFileToField($field, $path)
  {
    $field = $this->fixStepArgument($field);
    $this->getSession()->getPage()->attachFileToField($field, realpath(self::AVATAR_DIR . $path));
  }

  /**
   * @Then /^the avatar img tag should( [^"]*)? have the "([^"]*)" data url$/
   */
  public function theAvatarImgTagShouldHaveTheDataUrl($not, $name)
  {
    $name = trim($name);
    $not = trim($not);

    $source = $this->getSession()->getPage()->find("css", "#profile-avatar > img")->getAttribute("src");
    $source = trim($source, "\"");
    $styleHeader = $this->getSession()->getPage()->find("css", "#menu .img-avatar")->getAttribute("style");
    $sourceHeader = preg_replace("/(.+)url\(([^)]+)\)(.+)/", "\\2", $styleHeader);
    $sourceHeader = trim($sourceHeader, "\"");
    
    switch($name) {
      case "logo.png":
        $logoUrl = "data:image/png;base64," . base64_encode(file_get_contents(self::AVATAR_DIR . "logo.png"));
        $isSame = (($source == $logoUrl) && ($sourceHeader == $logoUrl));
        $not == "not" ? assertFalse($isSame) : assertTrue($isSame);
        break;

      case "fail.tif":
        $failUrl = "data:image/tiff;base64," . base64_encode(file_get_contents(self::AVATAR_DIR . "fail.tif"));
        $isSame = (($source == $failUrl) && ($sourceHeader == $failUrl));
        $not == "not" ? assertFalse($isSame) : assertTrue($isSame);
        break;

      default:
        assertTrue(false);
    }

  }

  /**
   * @Given /^the element "([^"]*)" should be visible$/
   */
  public function theElementShouldBeVisible($element)
  {
    $element = $this->getSession()->getPage()->find("css", $element);
    assertNotNull($element);
    assertTrue($element->isVisible());
  }

  /**
   * @Given /^the element "([^"]*)" should not be visible$/
   */
  public function theElementShouldNotBeVisible($element)
  {
    $element = $this->getSession()->getPage()->find("css", $element);
    assertNotNull($element);
    assertFalse($element->isVisible());
  }

  /**
   * @When /^I press enter in the search bar$/
   */
  public function iPressEnterInTheSearchBar()
  {
    $this->getSession()->evaluateScript("$('#searchbar').trigger($.Event( 'keypress', { which: 13 } ))");
    $this->getSession()->wait(5000, '(typeof window.search != "undefined") && (window.search.searchPageLoadDone == true)');
  }

    /**
     * @When /^I click the "([^"]*)" button$/
     */
    public function iClickTheButton($arg1)
    {
        $arg1 = trim($arg1);
        $page = $this->getSession()->getPage();
        $button = null;

        switch($arg1) {
            case "login":
                $button = $page->find("css", "#btn-login");
                break;
            case "logout":
                $button = $page->find("css", "#btn-logout");
                break;
            default:
                assertTrue(false);
        }

        $button->click();

    }

    /**
     * @When /^I trigger Facebook login with auth_type '([^']*)'$/
     */
    public function iTriggerFacebookLogin($arg1)
    {
      $this->assertElementOnPage('#btn-login');
      $this->iClickTheButton('login');
      $this->assertPageAddress('/pocketcode/login');
      $this->assertElementOnPage('#header-logo');
      $this->assertElementOnPage('#btn-login_facebook');
      $this->getSession()->executeScript('document.getElementById("facebook_auth_type").type = "text";');
      $this->getSession()->getPage()->findById('facebook_auth_type')->setValue($arg1);
      $this->clickLink('btn-login_facebook');
      $this->getSession()->wait(2000);
  }

    /**
     * @When /^I trigger Google login with approval prompt "([^"]*)"$/
     */

    public function iTriggerGoogleLogin($arg1)
    {
        $this->assertElementOnPage('#btn-login');
        $this->iClickTheButton('login');
        $this->assertPageAddress('/pocketcode/login');
        $this->assertElementOnPage('#header-logo');
        $this->assertElementOnPage('#btn-login_google');
        $this->getSession()->executeScript('document.getElementById("gplus_approval_prompt").type = "text";');
        $this->getSession()->getPage()->findById('gplus_approval_prompt')->setValue($arg1);
        $this->clickLink('btn-login_google');
        $this->clickLink('btn-login_google');
        $this->getSession()->wait(2500);
    }

  /**
   * @Then /^there should be "([^"]*)" programs in the database$/
   */
  public function thereShouldBeProgramsInTheDatabase($arg1)
  {
    /**
     * @var $program_manager \Catrobat\AppBundle\Entity\ProgramManager
     */
    $program_manager = $this->kernel->getContainer()->get('programmanager');
    $programs = $program_manager->findAll();

    assertEquals($arg1, count($programs));
  }

  /**
   * @Given /^there are starter programs:$/
   */
  public function thereAreStarterPrograms(TableNode $table)
  {
    /**
     * @var $program \Catrobat\AppBundle\Entity\Program
     * @var $starter \Catrobat\AppBundle\Entity\StarterCategory
     */
    $em = $this->kernel->getContainer()->get('doctrine')->getManager();

    $starter = new StarterCategory();
    $starter->setName("Games");
    $starter->setAlias("games");
    $starter->setOrder(1);

    $programs = $table->getHash();
    for($i = 0; $i < count($programs); $i ++) {
      $user = $em->getRepository('AppBundle:User')->findOneBy(array (
        'username' => $programs[$i]['owned by']
      ));
      $program = new Program();
      $program->setUser($user);
      $program->setName($programs[$i]['name']);
      $program->setDescription($programs[$i]['description']);
      $program->setViews($programs[$i]['views']);
      $program->setDownloads($programs[$i]['downloads']);
      $program->setUploadedAt(new \DateTime($programs[$i]['upload time'], new \DateTimeZone('UTC')));
      $program->setCatrobatVersion(1);
      $program->setCatrobatVersionName($programs[$i]['version']);
      $program->setLanguageVersion(1);
      $program->setUploadIp("127.0.0.1");
      $program->setRemixCount(0);
      $program->setFilesize(0);
      $program->setVisible(isset($programs[$i]['visible']) ? $programs[$i]['visible']=="true" : true);
      $program->setUploadLanguage("en");
      $program->setApproved(false);
      $em->persist($program);

      $starter->addProgram($program);
    }

    $em->persist($starter);
    $em->flush();
  }

  /**
   * @When /^I switch to popup window$/

   */
  public function iSwitchToPopupWindow()
  {
      $this->getSession()->wait(3000);
      $window_names = $this->getSession()->getDriver()->getWindowNames();
      foreach ($window_names as $name) {
          echo $name;
          $this->getSession()->switchToWindow($name);
      }
      $this->getSession()->wait(1000);
  }


    /**
     * @Then /^I log in to Facebook with email "([^"]*)" and password "([^"]*)"$/
     */
    public function iLogInToFacebookWithEmailAndPassword($arg1, $arg2)
    {
        $this->getSession()->wait(1000);
        if($this->getSession()->getPage()->find('css', '#facebook') && $this->getSession()->getPage()->find('css', '#login_form')) {
            echo 'facebook login form appeared';
            $page = $this->getSession()->getPage();
            $page->fillField('email',$arg1);
            $page->fillField('pass',$arg2);
            $button = $page->findById('u_0_2');
            assertTrue($button != null);
            $button->press();
        } else if($this->getSession()->getPage()->find('css', '#facebook') && $this->getSession()->getPage()->find('css', '#u_0_1')) {
            echo 'facebook reauthentication login form appeared';
            $page = $this->getSession()->getPage();
            $page->fillField('pass',$arg2);
            $button = $page->findById('u_0_0');
            assertTrue($button != null);
            $button->press();
        } else {
            assertTrue(false, 'No Facebook form appeared!');
        }
        $this->getSession()->switchToWindow(null);
        $this->getSession()->wait(4000);
    }

    /**
     * @Then /^I log in to Google with email "([^"]*)" and password "([^"]*)"$/
     */
    public function iLogInToGoogleWithEmailAndPassword($arg1, $arg2)
    {
        $this->getSession()->wait(1000);
        if($this->getSession()->getPage()->find('css', '#approval_container') && $this->getSession()->getPage()->find('css', '#submit_approve_access')) {
            echo 'Google Approve Access form appeared';
            $page = $this->getSession()->getPage();
            $button = $page->findById('submit_approve_access');
            assertTrue($button != null);
            $button->press();
        } else if($this->getSession()->getPage()->find('css', '.google-header-bar centered') && $this->getSession()->getPage()->find('css', '.signin-card clearfix')) {
            echo 'Google login form appeared';
            $page = $this->getSession()->getPage();

            $page->fillField('Email',$arg1);
            $page->fillField('Passwd',$arg2);
            $button = $page->findById('signIn');
            assertTrue($button != null);
            $button->press();
            $this->getSession()->wait(2000);

            $accept_button = $page->findById('submit_approve_access');
            assertTrue($accept_button != null);
            $accept_button->press();
        } else {
            assertTrue(false, 'No Google form appeared!');
        }

        $this->getSession()->switchToWindow(null);
        $this->getSession()->wait(4000);
    }

    /**
     * @Then /^there is a user in the database:$/
     */
    public function thereIsAUserInTheDatabase(TableNode $table)
    {
        /**
         * @var $user_manager \Catrobat\AppBundle\Entity\UserManager
         */
        $user_manager = $this->kernel->getContainer()->get('usermanager');
        $users = $table->getHash();
        $user = null;
        for($i = 0; $i < count($users); $i++)
        {
            $user = $user_manager->findUserByUsername($users[$i]["name"]);
            assertNotNull($user);
            assertTrue($user->getUsername() == $users[$i]["name"], 'Name wrong');
            assertTrue($user->getEmail() == $users[$i]["email"], 'E-Mail wrong');
            assertTrue($user->getCountry() == $users[$i]["country"], 'Country wrong');
            if($user->getFacebookUid() != ''){
                assertTrue($user->getFacebookAccessToken() != '', 'no Facebook access token present');
                assertTrue($user->getFacebookUid() == $users[$i]["facebook_uid"], 'Facebook UID wrong');
                assertTrue($user->getFacebookName() == $users[$i]["facebook_name"], 'Facebook name wrong');
            }
            if($user->getGplusUid() != ''){
                assertTrue($user->getGplusAccessToken() != '', 'no GPlus access token present');
                assertTrue($user->getGplusIdToken() != '', 'no GPlus id token present');
                assertTrue($user->getGplusRefreshToken() != '', 'no GPlus refresh token present');
                assertTrue($user->getGplusUid() == $users[$i]["google_uid"], 'Google UID wrong');
                assertTrue($user->getGplusName() == $users[$i]["google_name"], 'Google name wrong');
            }
        }
    }

    /**
     * @When /^I logout$/
     */
    public function iLogout()
    {
        $this->getSession()->getPage()->find("css", ".btn show-nav-dropdown")->click();
        $this->assertElementOnPage(".img-author-big");
        $this->getSession()->getPage()->find("css", ".img-author-big")->click();

    }

    /**
     * @Then /^I should not be logged in$/
     */
    public function iShouldNotBeLoggedIn()
    {
        $this->assertElementOnPage("#logo");
        $this->assertElementOnPage("#btn-login");
        $this->assertElementNotOnPage("#nav-dropdown");
    }

    /**
     * @When /^I wait for a second$/
     */
    public function iWaitForASecond()
    {
        $this->getSession()->wait(1000);
    }
}