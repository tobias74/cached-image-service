<?php 

abstract class FlyVideoMongoMapper extends \BrokenPottery\AbstractMongoMapper
{
    protected function declareDataMap()
    {
        $dataMap = new DataMap();
        $dataMap->addColumn('id', 'id');
        $dataMap->addColumn('user_id', 'userId');
        $dataMap->addColumn('requested_file_id', 'requestedFileId');
        $dataMap->addColumn('request_parameters', 'requestParameters');

        $dataMap->addColumn('transcoding_status', 'transcodingStatus');
        
        $dataMap->addColumn('fly_video_path_mp4', 'flyVideoPathMp4');
        $dataMap->addColumn('fly_video_path_ogv', 'flyVideoPathOgv');
        $dataMap->addColumn('fly_video_path_webm', 'flyVideoPathWebm');
        $dataMap->addColumn('fly_video_path_jpg', 'flyVideoPathJpg');
                                
        $this->dataMap = $dataMap;  
    }

    
        
    
    public function produceEmptyEntity()
    {
        $file = new FlyVideo();
        return $file;
    }
}


class StationFlyVideoMongoMapper extends FlyVideoMongoMapper
{
    
    protected function declareCollectionName()
    {
        $this->collectionName = "station_fly_videos";    
    }
  
  
}
