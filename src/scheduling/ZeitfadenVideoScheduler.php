<?php
namespace CachedImageService;


class OldVideoScheduler
{
  public function scheduleVideoTranscoding($id)
  {
    error_log('inside the ZeitfadenVideoScheduler scheduling');
    
    $schedulerUrl = 'http://scheduler.zeitfaden.com/task/schedule/queueName/videoFlyService?url=';
    $callbackUrl = 'flyservice.zeitfaden.com/video/transcode/flyId/'.$id;
    error_log($schedulerUrl.$callbackUrl);
    $r = new HttpRequest($schedulerUrl.$callbackUrl, HttpRequest::METH_GET);
    $r->send();
  } 
  
}


