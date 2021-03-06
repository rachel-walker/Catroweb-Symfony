<?php
namespace Catrobat\AppBundle\Services;

use Catrobat\AppBundle\Exceptions\InvalidCatrobatFileException;
use Catrobat\AppBundle\StatusCode;
use Symfony\Component\Finder\Finder;

class ExtractedCatrobatFile
{
  protected $path;
  protected $web_path;
  protected $dir_hash;
  protected $program_xml_properties;

  public function __construct($base_dir, $base_path, $dir_hash)
  {
    $this->path = $base_dir;
    $this->dir_hash = $dir_hash;
    $this->web_path = $base_path;
    
    if (!file_exists($base_dir . "code.xml"))
    {
      throw new InvalidCatrobatFileException("No code.xml found!", StatusCode::PROJECT_XML_MISSING);
    }
    
    $this->program_xml_properties = @simplexml_load_file($base_dir . "code.xml");
    if ($this->program_xml_properties === false)
    {
      throw new InvalidCatrobatFileException("code.xml is not a valid xml file!",StatusCode::INVALID_XML);
    }
  }

  public function getName()
  {
    return (string)$this->program_xml_properties->header->programName;
  }
  
  public function getLanguageVersion()
  {
    return (string)$this->program_xml_properties->header->catrobatLanguageVersion;
  }
  
  public function getDescription()
  {
    return (string)$this->program_xml_properties->header->description;
  }

  public function getDirHash()
  {
    return $this->dir_hash;
  }

  public function getContainingImagePaths()
  {
    $finder = new Finder();
    $finder->files()->in($this->path."images/");
    $file_paths = array();
    foreach($finder as $file)
    {
        $file_paths[] = "/".$this->web_path."images/".$file->getFilename();
    }
    return $file_paths;
  }

  public function getContainingSoundPaths()
  {
    $finder = new Finder();
    $finder->files()->in($this->path."sounds/");
    $file_paths = array();
    foreach($finder as $file)
    {
        $file_paths[] = "/".$this->web_path."sounds/".$file->getFilename();
    }
    return $file_paths;
  }
  
  public function getContainingStrings()
  {
      $xml = file_get_contents($this->path . "code.xml");
      $matches = array();
      preg_match_all("#>(.*[a-zA-Z].*)<#", $xml, $matches);
      return array_unique($matches[1]);
  }
  
  public function getScreenshotPath()
  {
    $screenshot_path = null;
    if (is_file($this->path . "screenshot.png"))
    {
      $screenshot_path = $this->path . "screenshot.png";
    }
    else if (is_file($this->path . "manual_screenshot.png"))
    {
      $screenshot_path = $this->path . "manual_screenshot.png";
    }
    else if (is_file($this->path . "automatic_screenshot.png"))
    {
      $screenshot_path = $this->path . "automatic_screenshot.png";
    }
    return $screenshot_path;
  }
  
  public function getApplicationVersion()
  {
    return (string)$this->program_xml_properties->header->applicationVersion;
  }
  
  public function getPath()
  {
    return $this->path;
  }
  
  public function getProgramXmlProperties()
  {
    return $this->program_xml_properties;
  }

  public function saveProgramXmlProperties()
  {
    $this->program_xml_properties->asXML($this->path . "code.xml");
  }

}