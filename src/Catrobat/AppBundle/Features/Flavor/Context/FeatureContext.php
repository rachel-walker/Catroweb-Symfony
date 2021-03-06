<?php

namespace Catrobat\AppBundle\Features\Flavor\Context;

use Behat\Gherkin\Node\TableNode;
use Catrobat\AppBundle\Entity\RudeWord;
use Catrobat\AppBundle\Features\Helpers\BaseContext;
use Catrobat\AppBundle\Entity\User;
use Catrobat\AppBundle\Entity\Program;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Catrobat\AppBundle\Services\TokenGenerator;
use Catrobat\AppBundle\Services\CatrobatFileCompressor;
use Catrobat\AppBundle\Entity\FeaturedProgram;
use Catrobat\AppBundle\Entity\ProgramManager;

require_once 'PHPUnit/Framework/Assert/Functions.php';

/**
 * Feature context.
 */
class FeatureContext extends BaseContext
{

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////// Support Functions

  private function getStandardProgramFile()
  {
    $filepath = self::FIXTUREDIR . "test.catrobat";
    assertTrue(file_exists($filepath), "File not found");
    return new UploadedFile($filepath, "test.catrobat");
  }

  private function getPhiroProProgramFile()
  {
    $filepath = $this->generateProgramFileWith(array('applicationName' => 'Pocket Phiro'));
    assertTrue(file_exists($filepath), "File not found");
    return new UploadedFile($filepath, "program_generated.catrobat");
  }

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////

    /**
     * @When /^I upload a catrobat program with the phiro app$/
     */
    public function iUploadACatrobatProgramWithThePhiroProApp()
    {
        $user = $this->insertUser();
        $program = $this->getPhiroProProgramFile();
        $response = $this->upload($program, $user);
        assertEquals(200, $response->getStatusCode(), "Wrong response code. " . $response->getContent());
    }

    /**
     * @Then /^the program should be flagged as phiro$/
     */
    public function theProgramShouldBeFlaggedAsPhiroPro()
    {
        $program_manager = $this->getProgramManger();
        $program = $program_manager->find(1);
        assertNotNull($program, "No program added");
        assertEquals("pocketphiropro", $program->getFlavor(), "Program is NOT flagged a phiro");
    }

    /**
     * @When /^I upload a standard catrobat program$/
     */
    public function iUploadAStandardCatrobatProgram()
    {
        $user = $this->insertUser();
        $program = $this->getStandardProgramFile();
        $response = $this->upload($program, $user);
        assertEquals(200, $response->getStatusCode(), "Wrong response code. " . $response->getContent());
    }

    /**
     * @Then /^the program should not be flagged as phiro$/
     */
    public function theProgramShouldNotBeFlaggedAsPhiroPro()
    {
        $program_manager = $this->getProgramManger();
        $program = $program_manager->find(1);
        assertNotNull($program, "No program added");
        assertNotEquals("pocketphiropro", $program->getFlavor(), "Program is flagged a phiro");
    }

    /**
     * @When /^I get the recent programs with "([^"]*)"$/
     * @When /^I get the most downloaded programs with "([^"]*)"$/
     * @When /^I get the most viewed programs with "([^"]*)"$/
     *
     */
    public function iGetTheMostProgramsWith($url)
    {
        $this->getClient()->request('GET', $url);
    }

    /**
     * @Then /^I should get following programs:$/
     */
    public function iShouldGetFollowingPrograms(TableNode $table)
    {
      $response = $this->getClient()->getResponse();
      assertEquals(200, $response->getStatusCode());
      $responseArray = json_decode($response->getContent(), true);
      $returned_programs = $responseArray['CatrobatProjects'];
      $expected_programs = $table->getHash();
      assertEquals(count($expected_programs), count($returned_programs), "Wrong number of returned programs");
      for($i = 0; $i < count($expected_programs); $i ++)
      {
          $found = false;
          for($j = 0; $j < count($returned_programs); $j ++)
          {
              if ($expected_programs[$i]["name"] === $returned_programs[$j]["ProjectName"])
              {
                  $found = true;
              }
          }
        assertTrue($found, $expected_programs[$i]["name"] . " was not found in the returned programs");
      }
    }

    /**
     * @Given /^there are programs:$/
     */
    public function thereArePrograms(TableNode $table)
    {
      $programs = $table->getHash();
      for($i = 0; $i < count($programs); $i ++)
      {

        $config = array(
          'name' => $programs[$i]['name'],
          'flavor' => $programs[$i]['flavor']
        );

        $this->insertProgram(null, $config);
      }
    }

    /**
     * @Given /^All programs are from the same user$/
     */
    public function allProgramsAreFromTheSameUser()
    {
      ///
    }

    /**
     * @When /^I get the user\'s programs with "([^"]*)"$/
     */
    public function iGetTheUserSProgramsWith($url)
    {
      $this->getClient()->request('GET', $url, array('user_id' => 1));
    }
}
