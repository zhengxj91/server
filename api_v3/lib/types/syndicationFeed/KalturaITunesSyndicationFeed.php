<?php
/**
 * @package api
 * @subpackage objects
 */
class KalturaITunesSyndicationFeed extends KalturaBaseSyndicationFeed
{
        /**
         * feed description
         * 
         * @var string
         */
        public $feedDescription;
        
        /**
         * feed language
         * 
         * @var string
         */
        public $language;
        
        /**
         * feed landing page (i.e publisher website)
         * 
         * @var string
         */
        public $feedLandingPage;
        
        /**
         * author/publisher name
         * 
         * @var string
         */
        public $ownerName;
        
        /**
         * publisher email
         * 
         * @var string
         */
        public $ownerEmail;
        
        /**
         * podcast thumbnail
         * 
         * @var string
         */
        public $feedImageUrl;

        /**
         *
         * @var KalturaITunesSyndicationFeedCategories
         * @readonly
         */
        public $category;        

        /**
         *
         * @var KalturaITunesSyndicationFeedAdultValues
         */
        public $adultContent;
        
        /**
         *
         * @var string
         */
        public $feedAuthor;

	/**
	 * @var bool
	 */
	public $enforceFeedAuthor;

        /**
	 * true in case you want to enfore the palylist order on the 
	 * @var KalturaNullableBoolean
	 */
	public $enforceOrder;
        
        
	function __construct()
	{
		$this->type = KalturaSyndicationFeedType::ITUNES;
        }

	private static $mapBetweenObjects = array
	(
                "feedDescription",
                "language",
                "feedLandingPage",
                "ownerName",
                "ownerEmail",
                "feedImageUrl",
                "adultContent",
                "feedAuthor",
		"enforceOrder",
		"enforceFeedAuthor",
	);
	
	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), self::$mapBetweenObjects);
	}

	public function toInsertableObject($object_to_fill = null, $props_to_skip = array())
	{
		if($this->enforceFeedAuthor && is_null($this->feedAuthor))
			throw new KalturaAPIException(KalturaErrors::ENFORCE_ITUNES_FEED_AUTHOR);
		return parent::toInsertableObject($object_to_fill, $props_to_skip); // TODO: Change the autogenerated stub
	}
}
