<?php
namespace CachedImageService;

class InstantScheduler
{
  public function setCachedVideoService($flyService)
  {
    $this->flyService = $flyService;
  }
 
  protected function getCachedVideoService()
  {
    return $this->flyService;
  }

  public function scheduleVideoTranscoding($id)
  {
    $this->getCachedVideoService()->performTranscoding($id);
  } 
  
}
