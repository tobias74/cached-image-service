<?php 

class FlyVideo extends DomainObject
{
  protected $userId;
  protected $requestedFileId;
  protected $requestParameters;
  protected $flyVideoPathMp4;
  protected $flyVideoPathOgv;
  protected $flyVideoPathWebm;
  protected $flyVideoPathJpg;
  protected $transcodingStatus;
        
  protected function declareSynthesizedProperties()
  {
    return array(
      'userId',
      'transcodingStatus',
      'requestedFileId',
      'requestParameters',
      'flyVideoPathMp4',
      'flyVideoPathOgv',
      'flyVideoPathWebm',
      'flyVideoPathJpg'
    );
  }
  
  
  
  
}



