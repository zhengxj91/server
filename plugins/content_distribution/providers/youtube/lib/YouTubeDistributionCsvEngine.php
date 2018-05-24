<?php
/**
 * @package plugins.youTubeDistribution
 * @subpackage lib
 */
class YouTubeDistributionCsvEngine extends YouTubeDistributionRightsFeedEngine
{
	/**
	 * @param KalturaDistributionJobData $data
	 * @param KalturaYouTubeDistributionProfile $distributionProfile
	 * @param KalturaYouTubeDistributionJobProviderData $providerData
	 */
	protected function handleSubmit(KalturaDistributionJobData $data, KalturaYouTubeDistributionProfile $distributionProfile, KalturaYouTubeDistributionJobProviderData $providerData)
	{
		$videoFilePath = $providerData->videoAssetFilePath;
		$thumbnailFilePath = $providerData->thumbAssetFilePath;

		if (!$videoFilePath)
			throw new KalturaDistributionException('No video asset to distribute, the job will fail');

		if (!file_exists($videoFilePath))
			throw new KalturaDistributionException('The file ['.$videoFilePath.'] was not found (probably not synced yet), the job will retry');

		$csvMap = unserialize($providerData->submitCsvMap);
		$videoCsv = implode(',' ,array_keys($csvMap )) .'\n';
		$videoCsv .= implode(',' ,array_values($csvMap)) .'\n';

		$sftpManager = $this->getSFTPManager($distributionProfile);
		$sftpManager->filePutContents($providerData->sftpDirectory.'/'.$providerData->sftpMetadataFilename, $videoCsv );

		$data->sentData = $videoCsv;
		$data->results = 'none'; // otherwise kContentDistributionFlowManager won't save sentData

		// upload the video
		$videoSFTPPath = $providerData->sftpDirectory.'/'.pathinfo($videoFilePath, PATHINFO_BASENAME);
		$sftpManager->putFile($videoSFTPPath, $videoFilePath);

		// upload the thumbnail if exists
		if (file_exists($thumbnailFilePath))
		{
			$thumbnailSFTPPath = $providerData->sftpDirectory.'/'.pathinfo($thumbnailFilePath, PATHINFO_BASENAME);
			$sftpManager->putFile($thumbnailSFTPPath, $thumbnailFilePath);
		}

		$this->addCaptions($providerData, $sftpManager, $data);
		$this->setDeliveryComplete($sftpManager, $providerData->sftpDirectory);
	}

	/* (non-PHPdoc)
	 * @see IDistributionEngineCloseSubmit::closeSubmit()
	 */
	public function closeSubmit(KalturaDistributionSubmitJobData $data)
	{
		$statusXml = $this->fetchStatusXml($data, $data->distributionProfile, $data->providerData);

		if ($statusXml === false) // no status yet
		{
			// try to get batch status xml to see if there is an internal error on youtube's batch
			$batchStatus = $this->fetchBatchStatus($data, $data->distributionProfile, $data->providerData);
			if ($batchStatus)
				throw new Exception('Internal failure on YouTube, internal_failure-status.xml was found. Error ['.$batchStatus.']');

			return false; // return false to recheck again on next job closing iteration
		}
			
		$statusParser = new YouTubeDistributionRightsFeedLegacyStatusParser($statusXml);
		$status = $statusParser->getStatusForAction('Submit reference');

		// maybe we didn't submit a reference, so let's check the file status
		if (!$status)
			$status = $statusParser->getStatusForAction('Process file');

		if ($status != 'Success')
		{
			$errors = $statusParser->getErrorsSummary();
			throw new Exception('Distribution failed with status ['.$status.'] and errors ['.implode(',', $errors).']');
		}
			
		$referenceId = $statusParser->getReferenceId();
		$assetId = $statusParser->getAssetId();
		$videoId = $statusParser->getVideoId();

		$captionCsvMap = unserialize($data->providerData->captionsCsvMap);
		if ($videoId && !empty($captionCsvMap))
		{
			$sftpManager = $this->getSFTPManager($data->distributionProfile);
			$captionsContent = "video_id,language,caption_file\n";
			foreach ($captionCsvMap as $captionItem )
			{
				$row = $videoId ."," . $captionItem['language'] ."," . $captionItem['language'] ."\n" ;
				$captionsContent .= $row ."\n";
			}
			$sftpManager->filePutContents( $data->providerData->sftpDirectory . '/' .  $data->providerData->sftpMetadataFilename, $captionsContent);

			$this->setDeliveryComplete($sftpManager,  $data->providerData->sftpDirectory);
		}

		$remoteIdHandler = new YouTubeDistributionRemoteIdHandler();
		$remoteIdHandler->setVideoId($videoId);
		$remoteIdHandler->setAssetId($assetId);
		$remoteIdHandler->setReferenceId($referenceId);
		$data->remoteId = $remoteIdHandler->getSerialized();

		$providerData = $data->providerData;
		$newPlaylists = $this->syncPlaylists($videoId, $providerData);
		$providerData->currentPlaylists = $newPlaylists;
		return true;
	}

	/**
	 * @param KalturaDistributionJobData $data
	 * @param KalturaYouTubeDistributionProfile $distributionProfile
	 * @param KalturaYouTubeDistributionJobProviderData $providerData
	 */
	protected function handleUpdate(KalturaDistributionJobData $data, KalturaYouTubeDistributionProfile $distributionProfile, KalturaYouTubeDistributionJobProviderData $providerData)
	{
		$thumbnailFilePath = $providerData->thumbAssetFilePath;

		$sftpManager = $this->getSFTPManager($distributionProfile);
		$updateCsvMap = unserialize($providerData->updateCsvMap);
		$videoCsv = implode(',' ,array_keys($updateCsvMap)) .'\n';
		$videoCsv .= implode(',' ,array_values($updateCsvMap)) .'\n';

		$sftpManager = $this->getSFTPManager($distributionProfile);
		$sftpManager->filePutContents($providerData->sftpDirectory.'/'.$providerData->sftpMetadataFilename, $videoCsv );
		$data->sentData = $videoCsv;
		$data->results = 'none'; // otherwise kContentDistributionFlowManager won't save sentData

		// upload the thumbnail if exists
		if (file_exists($thumbnailFilePath))
		{
			$thumbnailSFTPPath = $providerData->sftpDirectory.'/'.pathinfo($thumbnailFilePath, PATHINFO_BASENAME);
			$sftpManager->putFile($thumbnailSFTPPath, $thumbnailFilePath);
		}

		$this->setDeliveryComplete($sftpManager, $providerData->sftpDirectory);
	}

	/* (non-PHPdoc)
	 * @see IDistributionEngineCloseUpdate::closeUpdate()
	 */
	public function closeUpdate(KalturaDistributionUpdateJobData $data)
	{
		$statusXml = $this->fetchStatusXml($data, $data->distributionProfile, $data->providerData);

		if ($statusXml === false) // no status yet
			return false;
			
		$statusParser = new YouTubeDistributionRightsFeedLegacyStatusParser($statusXml);
		$status = $statusParser->getStatusForAction('Update video');
		if (is_null($status))
			throw new Exception('Status could not be found after distribution update');
		
		if ($status != 'Success')
			throw new Exception('Update failed with status ['.$status.']');

		$remoteIdHandler = YouTubeDistributionRemoteIdHandler::initialize($data->remoteId);
		$videoId = $remoteIdHandler->getVideoId();

		$captionCsvMap = unserialize($data->providerData->captionsCsvMap);
		if ($videoId && !empty($captionCsvMap ))
		{
			$sftpManager = $this->getSFTPManager($data->distributionProfile);
			$captionsContent = "video_id,language,caption_file\n";
			foreach ($captionCsvMap as $captionItem )
			{
				$row = $videoId ."," . $captionItem['language'] ."," . $captionItem['language'] ."\n" ;
				$captionsContent .= $row ."\n";
			}
			$sftpManager->filePutContents( $data->providerData->sftpDirectory . '/' .  $data->providerData->sftpMetadataFilename, $captionsContent);

			$this->setDeliveryComplete($sftpManager,  $data->providerData->sftpDirectory);
		}

		$providerData = $data->providerData;
		$newPlaylists = $this->syncPlaylists($videoId, $providerData);
		$providerData->currentPlaylists = implode(',',$newPlaylists);

		return true;
	}

	/**
	 * Deleting a video is done via api call
	 * @param KalturaDistributionJobData $data
	 * @param KalturaYouTubeDistributionProfile $distributionProfile
	 * @param KalturaYouTubeDistributionJobProviderData $providerData
	 */
	protected function handleDelete(KalturaDistributionJobData $data, KalturaYouTubeDistributionProfile $distributionProfile, KalturaYouTubeDistributionJobProviderData $providerData)
	{
		$videoIdsToDelete = unserialize($providerData->deleteVideoIds);

		if (!empty($videoIdsToDelete))
			return;

		$clientId = $providerData->googleClientId;
		$clientSecret   = $providerData->googleClientSecret;
		$tokenData = $providerData->googleTokenData;

		$youtubeService = YouTubeDistributionGoogleClientHelper::getYouTubeService($clientId, $clientSecret, $tokenData);
		foreach($videoIdsToDelete as $videoIdToDelete)
			$youtubeService->videos->delete($videoIdToDelete);

		$data->sentData = implode(',',$videoIdsToDelete);
		$data->results = 'none'; // otherwise kContentDistributionFlowManager won't save sentData
	}
}