<?php
namespace Catrobat\AppBundle\Services;

class TokenGenerator
{
  
  function __construct()
  {
  }
  
  function generateToken()
  {
    return md5(uniqid(rand(),false));
  }
  
}
