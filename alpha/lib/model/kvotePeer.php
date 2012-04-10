<?php

/**
 * Subclass for performing query and update operations on the 'kvote' table.
 *
 * 
 *
 * @package Core
 * @subpackage model
 */ 
class kvotePeer extends BasekvotePeer
{
    public static function setDefaultCriteriaFilter()
    {
        if ( self::$s_criteria_filter == null )
		{
			self::$s_criteria_filter = new criteriaFilter ();
		}

		$c = new myCriteria();
		$c->add ( kvotePeer::STATUS, KVoteStatus::REVOKED, Criteria::NOT_EQUAL );
		
		self::$s_criteria_filter->setFilter ( $c );
    }
    
    
    public static function doSelectByEntryIdAndPuserId ($entryId, $partnerId, $puserId)
    {
        $kuser = self::getKuserFromPuserAndPartner($puserId, $partnerId);
        
        $c = new Criteria(); 
        $c->addAnd(kvotePeer::KUSER_ID, $kuser->getId(), Criteria::EQUAL);
        $c->addAnd(kvotePeer::ENTRY_ID, $entryId, Criteria::EQUAL);
        
        return self::doSelectOne($c);
    }
    
    protected static function getKuserFromPuserAndPartner($puserId, $partnerId, $shouldCreate = false)
	{
		$kuser = kuserPeer::getKuserByPartnerAndUid($partnerId, $puserId, true);
    		
		if ($kuser && $kuser->getStatus() !== KuserStatus::ACTIVE)
			throw new kCoreException(APIErrors::INVALID_USER_ID);
		
		return $kuser;
	}
	
	public static function enableExistingKVote ($entryId, $partnerId, $puserId)
	{
	    self::setUseCriteriaFilter(false);
	    
	    $kvote = self::doSelectByEntryIdAndPuserId($entryId, $partnerId, $puserId);
	    $kvote->setStatus(KVoteStatus::VOTED);
	    $affectedLines = $kvote->save();
	    
	    return $affectedLines;
	}
	
    public static function disableExistingKVote ($entryId, $partnerId, $puserId)
	{
	    $kvote = self::doSelectByEntryIdAndPuserId($entryId, $partnerId, $puserId);
        $kvote->setStatus(KVoteStatus::REVOKED);
	    $affectedLines = $kvote->save();
	    
	    return $affectedLines;
	    
	}
	
	public static function createKvote ($entryId, $partnerId, $puserId, $rank)
	{
	    $kvote = new kvote();
		$kvote->setEntryId($entryId);
		$kvote->setStatus(KVoteStatus::VOTED);
		$kuser = self::getKuserFromPuserAndPartner($puserId, $partnerId);
		if (!$kuser)
		{
		    $kuser = new kuser();
		    $kuser->setPuserId($puserId);
		    $kuser->setStatus(KuserStatus::ACTIVE);
		    $kuser->save();
		}
		$kvote->setKuserId($kuser->getId());
		$kvote->setRank($rank);
		$kvote->save();
	}
}
