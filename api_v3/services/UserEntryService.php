<?php

/**
 * @service userEntry
 * @package api
 * @subpackage services
 */
class UserEntryService extends KalturaBaseService {

	public function initService($serviceId, $serviceName, $actionName)
	{
		parent::initService($serviceId, $serviceName, $actionName);
	}

	/**
	 * Adds a user_entry to the Kaltura DB.
	 *
	 * @action add
	 * @param KalturaUserEntry $userEntry
	 * @return KalturaUserEntry
	 */
	public function addAction(KalturaUserEntry $userEntry)
	{
		$entry = entryPeer::retrieveByPK($userEntry->entryId);
		if (!$entry)
			throw new KalturaAPIException(KalturaErrors::INVALID_ENTRY_ID, $userEntry->entryId);

		$dbUserEntry = $userEntry->toInsertableObject(null, array('type'));
		if (kEntitlementUtils::getEntitlementEnforcement())
		{
			$privacyContexts = kEntitlementUtils::getKsPrivacyContextArray();
			$dbUserEntry->setPrivacyContext($privacyContexts[0]);
		}
		
		$dbUserEntry->save();

		$userEntry->fromObject($dbUserEntry, $this->getResponseProfile());

		return $userEntry;
	}

	/**
	 *
	 * @action update
	 * @param int $id
	 * @param KalturaUserEntry $userEntry
	 * @throws KalturaAPIException
	 */
	public function updateAction($id, KalturaUserEntry $userEntry)
	{
		$dbUserEntry = UserEntryPeer::retrieveByPK($id);
		if (!$dbUserEntry)
			throw new KalturaAPIException(KalturaErrors::INVALID_OBJECT_ID, $id);

		$dbUserEntry = $userEntry->toUpdatableObject($dbUserEntry);
		$dbUserEntry->save();
		
		$userEntry->fromObject($dbUserEntry);
		
		return $userEntry;
	}

	/**
	 * @action delete
	 * @param int $id
	 * @return KalturaUserEntry The deleted UserEntry object
 	 * @throws KalturaAPIException
	 */
	public function deleteAction($id)
	{
		$dbUserEntry = UserEntryPeer::retrieveByPK($id);
		if (!$dbUserEntry)
			throw new KalturaAPIException(KalturaErrors::INVALID_OBJECT_ID, $id);
		$dbUserEntry->setStatus(KalturaUserEntryStatus::DELETED);
		$dbUserEntry->save();

		$userEntry = KalturaUserEntry::getInstanceByType($dbUserEntry->getType());
		$userEntry->fromObject($dbUserEntry, $this->getResponseProfile());

		return $userEntry;

	}

	/**
	 * @action list
	 * @param KalturaUserEntryFilter $filter
	 * @param KalturaFilterPager $pager
	 * @return KalturaUserEntryListResponse
	 */
	public function listAction(KalturaUserEntryFilter $filter, KalturaFilterPager $pager = null)
	{
		if (!$filter)
		{
			$filter = new KalturaUserEntryFilter();
		}
		if (!$pager)
		{
			$pager = new KalturaFilterPager();
		}
		// return empty list when userId was not given
		if ( $this->getKs() && !$this->getKs()->isAdmin() && !kCurrentContext::$ks_uid ) {
		    return new KalturaUserEntryListResponse();
		}
		return $filter->getListResponse($pager, $this->getResponseProfile());
	}

	/**
	 * @action get
	 * @param string $id
	 * @return KalturaUserEntry
	 * @throws KalturaAPIException
	 */
	public function getAction($id)
	{
		$dbUserEntry = UserEntryPeer::retrieveByPK( $id );
		if(!$dbUserEntry)
			throw new KalturaAPIException(KalturaErrors::USER_ENTRY_NOT_FOUND, $id);

		$userEntry = KalturaUserEntry::getInstanceByType($dbUserEntry->getType());
		if (!$userEntry)
			return null;
		$userEntry->fromObject($dbUserEntry);
		return $userEntry;
	}
	
	/**
	 * @action bulkDelete
	 * @param string $id
	 * @return int
	 * @throws KalturaAPIException
	 */
	public function bulkDeleteAction(KalturaUserEntryFilter $filter)
	{
		if (!$filter->userIdEqual && !$filter->userIdIn && !$filter->entryIdEqual && !$filter->entryIdIn)
		{
			throw new KalturaAPIException(KalturaErrors::MUST_FILTER_ON_ENTRY_OR_USER);	
		}
		
		$ueFilter = new UserEntryFilter();
		if ($filter->userIdEqual)
		{
			$ueFilter->set("_eq_user_id", $filter->userIdEqual);
			$filter->userIdEqual = null;
		}
		if($filter->userIdIn)
		{
			$ueFilter->set("_in_user_id", $filter->userIdIn);
			$filter->userIdIn = null;
		}
		$ueFilter = $filter->toObject($ueFilter);
		if (kEntitlementUtils::getEntitlementEnforcement())
		{
			$privacyContexts = kEntitlementUtils::getKsPrivacyContextArray();
			$ueFilter->set("_eq_privacy_context", $privacyContexts[0]);
		}
		
		return kJobsManager::addDeleteJob(kCurrentContext::getCurrentPartnerId(), DeleteObjectType::USER_ENTRY, $ueFilter);
	}

}