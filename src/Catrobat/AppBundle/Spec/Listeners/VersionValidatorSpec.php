<?php

namespace Catrobat\AppBundle\Spec\Listeners;

use Catrobat\AppBundle\Services\ExtractedCatrobatFile;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Filesystem\Filesystem;


class VersionValidatorSpec extends ObjectBehavior
{

  function let($repository, $program_entity, $user)
  {
    $filesystem = new Filesystem();
    $filesystem->mirror(__SPEC_GENERATED_FIXTURES_DIR__."/base/", __SPEC_CACHE_DIR__."/base/" );
  }

  function it_is_initializable()
  {
      $this->shouldHaveType('Catrobat\AppBundle\Listeners\VersionValidator');
  }

  function it_checks_if_the_language_version_is_uptodate()
  {
    $xml = simplexml_load_file(__SPEC_CACHE_DIR__."/base/code.xml");
    $xml->header->catrobatLanguageVersion = "0.92";
    $this->validate($xml);
  }

  function it_throws_an_exception_if_languageversion_is_too_old()
  {
    $xml = simplexml_load_file(__SPEC_CACHE_DIR__."/base/code.xml");
    $xml->header->catrobatLanguageVersion = "0.90";
    $this->shouldThrow('Catrobat\AppBundle\Exceptions\InvalidCatrobatFileException')->duringValidate($xml);
  }
}
