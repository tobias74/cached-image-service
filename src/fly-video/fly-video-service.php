<?php 

class FlyVideoService
{
  
  public function __construct()
  {
    $this->collectionName = 'fly_videos';
    $this->mongoConnection = new \MongoClient();
    $this->mongoDb = $this->mongoConnection->fly_service;
    
    $name = $this->collectionName;
    $this->collection = $this->mongoDb->$name;
    
    $this->collection->ensureIndex(array('serialized_specification' => 1));
        
  }

  // common  
  public function getFlyGridFile($url, $flySpec)
  {
    $flyDocument = $this->getFly($url, $flySpec);
    $gridFS = $this->mongoDb->getGridFS();
    $fileDocument = $gridFS->findOne(array('metadata.fly_id' => $flyDocument['_id']));
    
    if (!$fileDocument)
    {
      throw new \Exception('not found'); 
    }
    
    return $fileDocument;
  }     
  
  
  
  public function getCachedVideoData($videoUrl, $flySpec)
  {
    try
    {
      $gridFile = $this->getFlyGridFile($videoUrl, $flySpec);
      $name='$id';
      return array(
        'gridFileId' => $gridFile->file['_id']->$name,
        'collectionName' => 'fly_service',
        'mongoServerIp' => $_SERVER['SERVER_NAME'],
        'done' => 1
      );
    }
    catch (\Exception $e)
    {
      error_log('send back default video file with message to wait: '.$e->getMessage());
      return array(
        'done' => 0
      );
    }
    
  }
  
  protected function createAndMergeFly($idUrl, $flySpec)
  {
    $timer = $this->profiler->startTimer('creating new fly');
    $flyDocument = $this->createFly($idUrl, $flySpec);
    $timer->stop();
    return $flyDocument;
  }
  
  
  public function setProfiler($profiler)
  {
    if (!is_object($profiler))
    {
      throw new \ErrorException('profiler is not an object?'.$profiler);
    }
    $this->profiler = $profiler;
  }
  
  
  //special
  public function getFly($videoIdUrl, $flySpec)
  {
    
    $serializedSpec = $flySpec->serialize();
    $flyDocument = $this->collection->findOne(array('video_id_url'=> $videoIdUrl, 'serialized_specification' => $serializedSpec));
    if (!$flyDocument)
    {
      error_log('did not find fly. now creating');
      $flyDocument = $this->createAndMergeFly($videoIdUrl, $flySpec);
    }
    else 
    {
      // if fly is empty too long, something went wrong, retry
      
      if (isset($flyDocument['created']) && (($flyDocument['created'] + 3600*24*1) < time()) && ($flyDocument['transcoding_status'] != 'done'))
      {
        error_log('found broken fly, rescheduling transcoding.');
        $this->collection->remove(array('_id'=> new MongoId($flyDocument['_id'])), array('justOne'=>true));
        $flyDocument = $this->createAndMergeFly($videoIdUrl, $flySpec);
      }

      error_log('found the fly');
      error_log(print_r($flyDocument,true));
      
    }
    return $flyDocument;
  }
  

  protected function createFly($videoIdUrl, $flySpec)
  {
    $timer = $this->profiler->startTimer('creating new fly-images');
    
    
    // continue here with video urls like below in line 59
    $document = array(
      'video_id_url' => $videoIdUrl,
      'transcoding_status' => 'scheduled',
      'created' => time(),
      'specification' => $flySpec->getHash(),
      'serialized_specification' => $flySpec->serialize()
    );
    
    $this->collection->insert($document);
    $this->scheduleTranscoding($document);
          
    $timer->stop();
        
        
    return $document;
  }

  protected function scheduleTranscoding($document)
  {
    $schedulerUrl = 'http://scheduler.zeitfaden.com/task/schedule/queueName/videoFlyService?url=';
    $callbackUrl = 'flyservice.zeitfaden.com/video/transcode/flyId/'.$document['_id'].'/format/'.$document['specification']['format'];
    error_log($schedulerUrl.$callbackUrl);
    $r = new HttpRequest($schedulerUrl.$callbackUrl, HttpRequest::METH_GET);
    $r->send();
  }
  
  
  public function performTranscoding($flyId)
  {
   
      
    $document = $this->collection->findOne(array('_id'=>new MongoId($flyId)));
    
    $sourceVideoFile = tempnam('/tmp','flysource');
    
    error_log('starting downadlon');
    exec('wget --output-document='.$sourceVideoFile.' '.$document['video_id_url']);
    error_log('finished ddownload');
    
    
    $targetVideoFile = tempnam('/tmp','flyfiles');
    
      
    $targetVideoFile = $targetVideoFile.'.'.$document['specification']['format'];    
    
//    $uniqueFileNameMp4 = $uniqueFileName.'.mp4';
//    $uniqueFileNameOgv = $uniqueFileName.'.ogv';
//    $uniqueFileNameWebm = $uniqueFileName.'.webm';
//    $uniqueFileNameJpg = $uniqueFileName.'.jpg';
    

    $command = dirname(__FILE__)."/scripts/convert_".$document['specification']['format']." $sourceVideoFile $targetVideoFile";
    
    error_log("executing ".$command);
    exec($command);
    error_log("and done it");  


    $gridFS = $this->mongoDb->getGridFS();
    $hash = array();
    $hash['fly_id'] = $document['_id'];
    $hash['fly_collection_name'] = $this->collectionName;
    $hash['fly_content_type'] = 'video/'.$document['specification']['format'];
    $hash['type'] = 'video/'.$document['specification']['format'];
    
    $gridFS->storeFile($targetVideoFile,array("metadata" => $hash));
    
    error_log('did store the file in gridfs');
    
    $document['transcoding_status'] = 'done';  
    $this->collection->save($document);
    
    unlink($sourceVideoFile);
    unlink($targetVideoFile);
    
    
  }

  
  public function deleteFlysForFile($file)
  {
    $flys = $this->flyRepository->getFlysForFile($file->getId(), $file->getUserId());
    
    foreach ($flys as $fly)
    {
      
      foreach ($fly->getFlyPaths() as $flyPath)
      {
        $fileName = $this->getShardFlyFolder($fly->getUserId()).$flyPath;

        if (!$this->systemService->file_exists($fileName))
        {
          throw new Exception("why does this file not exist?.");
        }
        else
        {
          $this->systemService->unlink($fileName);
        }
                
      }
      
      $this->flyRepository->delete($fly);
            
    }
  }
  
  
  //this is the optimized version:
  public function getMultipleUrlsForVideos($files, $flySpec)
  {
    $urls = array();
    $assocFiles = array();
    
    foreach($files as $file)
    {
      $assocFiles[$file->getId()] = $file;
    }
    
    $flys = $this->getMultipleFlys($files, $flySpec);
    
    foreach($flys as $fileId => $fly)
    {
      if ($assocFiles[$fileId]->getUserId() != $fly->getUserId())
      {
        throw new ErrorException('we would expect the corresponding userId in the fly'); 
      }
      
      $urls[$fileId]['mp4'] = "http://".$this->getShard($fly->getUserId())->getFlyFolderUrl().$fly->getFlyVideoPathMp4(); 
      $urls[$fileId]['ogv'] = "http://".$this->getShard($fly->getUserId())->getFlyFolderUrl().$fly->getFlyVideoPathOgv(); 
      $urls[$fileId]['webm'] = "http://".$this->getShard($fly->getUserId())->getFlyFolderUrl().$fly->getFlyVideoPathWebm(); 
      $urls[$fileId]['jpg'] = "http://".$this->getShard($fly->getUserId())->getFlyFolderUrl().$fly->getFlyVideoPathJpg(); 
    }
    
    return $urls;
    
  }
  
  
  public function getUrlForVideo($file, $flySpec)
  {
    $timer = $this->profiler->startTimer('getting fly video');
    $fly = $this->getFly($file, $flySpec);
    $timer->stop();
    
    $url = array();
    $url['mp4'] = "http://".$this->getShard($file->getUserId())->getFlyFolderUrl().$fly->getFlyVideoPathMp4();
    $url['ogv'] = "http://".$this->getShard($file->getUserId())->getFlyFolderUrl().$fly->getFlyVideoPathOgv();
    $url['webm'] = "http://".$this->getShard($file->getUserId())->getFlyFolderUrl().$fly->getFlyVideoPathWebm();
    $url['jpg'] = "http://".$this->getShard($file->getUserId())->getFlyFolderUrl().$fly->getFlyVideoPathJpg();
    
    return $url; 
  }
  
  
  
  
  
  
  
  

  
  
}





class FlyVideoSpecification
{
  protected $mode='none';
  public $format;
  public $quality;
  
  public function getMode()
  {
    return $this->mode;
  }
  
  public function setMode($val)
  {
    $this->mode = $val;
  }
  
  public function getHash()
  {
    return array(
      'quality' => $this->quality,
      'format' => $this->format
    );  
  }
  
  public function serialize()
  {
    return serialize($this->getHash());
  }
  
}


