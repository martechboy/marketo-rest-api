<?php
namespace CSD\Marketo;

// Guzzle
use CommerceGuys\Guzzle\Plugin\Oauth2\Oauth2Plugin;
use Guzzle\Common\Collection;
use Guzzle\Service\Client as GuzzleClient;
use Guzzle\Service\Description\ServiceDescription;

// Response classes
use CSD\Marketo\Response\AddOrRemoveLeadsToListResponse;
use CSD\Marketo\Response\CreateOrUpdateLeadsResponse;
use CSD\Marketo\Response\GetCampaignResponse;
use CSD\Marketo\Response\GetCampaignsResponse;
use CSD\Marketo\Response\GetLeadResponse;
use CSD\Marketo\Response\GetLeadsResponse;
use CSD\Marketo\Response\GetListResponse;
use CSD\Marketo\Response\GetListsResponse;
use CSD\Marketo\Response\IsMemberOfListResponse;

/**
 * Guzzle client for communicating with the Marketo REST API.
 *
 * @see http://developers.marketo.com/documentation/rest/
 * @author Daniel Chesterton <daniel@chestertondevelopment.com>
 */
class Client extends GuzzleClient
{
    /**
     * {@inheritdoc}
     */
    public static function factory($config = array())
    {
        $default = [
            'url' => false,
            'munchkin_id' => false,
            'version' => 1
        ];

        $required = ['client_id', 'client_secret', 'version'];
        $config = Collection::fromConfig($config, $default, $required);

        $url = $config->get('url');

        if (!$url) {
            $munchkin = $config->get('munchkin_id');

            if (!$munchkin) {
                throw new \Exception('Must provide either a URL or Munchkin code.');
            }

            $url = sprintf('https://%s.mktorest.com', $munchkin);
        }

        $grantType = new Credentials($url, $config->get('client_id'), $config->get('client_secret'));
        $auth = new Oauth2Plugin($grantType);

        $restUrl = sprintf('%s/rest/v%d', rtrim($url, '/'), $config->get('version'));

        $client = new self($restUrl, $config);
        $client->addSubscriber($auth);
        $client->setDescription(ServiceDescription::factory(__DIR__ . '/service.json'));

        return $client;
    }

    /**
     * Calls the CreateOrUpdateLeads command with the given action.
     *
     * @param string $action
     * @param array  $leads
     * @param string $lookupField
     * @param array  $args
     *
     * @see Client::createLeads()
     * @see Client::createOrUpdateLeads()
     * @see Client::updateLeads()
     * @see Client::createDuplicateLeads()
     *
     * @link http://developers.marketo.com/documentation/rest/createupdate-leads/
     *
     * @return CreateOrUpdateLeadsResponse
     */
    private function createOrUpdateLeadsCommand($action, $leads, $lookupField, $args)
    {
        $args['input'] = $leads;
        $args['action'] = $action;

        if (isset($lookupField)) {
            $args['lookupField'] = $lookupField;
        }

        return $this->getResult('createOrUpdateLeads', $args);
    }

    /**
     * Create the given leads.
     *
     * @param array  $leads
     * @param string $lookupField
     * @param array  $args
     * @see Client::createOrUpdateLeadsCommand()
     *
     * @link http://developers.marketo.com/documentation/rest/createupdate-leads/
     *
     * @return CreateOrUpdateLeadsResponse
     */
    public function createLeads($leads, $lookupField = null, $args = [])
    {
        return $this->createOrUpdateLeadsCommand('createOnly', $leads, $lookupField, $args);
    }

    /**
     * Update the given leads, or create them if they do not exist.
     *
     * @param array  $leads
     * @param string $lookupField
     * @param array  $args
     * @see Client::createOrUpdateLeadsCommand()
     *
     * @link http://developers.marketo.com/documentation/rest/createupdate-leads/
     *
     * @return CreateOrUpdateLeadsResponse
     */
    public function createOrUpdateLeads($leads, $lookupField = null, $args = [])
    {
        return $this->createOrUpdateLeadsCommand('createOrUpdate', $leads, $lookupField, $args);
    }

    /**
     * Update the given leads.
     *
     * @param array  $leads
     * @param string $lookupField
     * @param array  $args
     * @see Client::createOrUpdateLeadsCommand()
     *
     * @link http://developers.marketo.com/documentation/rest/createupdate-leads/
     *
     * @return CreateOrUpdateLeadsResponse
     */
    public function updateLeads($leads, $lookupField = null, $args = [])
    {
        return $this->createOrUpdateLeadsCommand('updateOnly', $leads, $lookupField, $args);
    }

    /**
     * Create duplicates of the given leads.
     *
     * @param array  $leads
     * @param string $lookupField
     * @param array  $args
     * @see Client::createOrUpdateLeadsCommand()
     *
     * @link http://developers.marketo.com/documentation/rest/createupdate-leads/
     *
     * @return CreateOrUpdateLeadsResponse
     */
    public function createDuplicateLeads($leads, $lookupField = null, $args = [])
    {
        return $this->createOrUpdateLeadsCommand('createDuplicate', $leads, $lookupField, $args);
    }

    /**
     * Get multiple lists.
     *
     * @param int|array $ids  Filter by one or more IDs
     * @param array     $args
     *
     * @link http://developers.marketo.com/documentation/rest/get-multiple-lists/
     *
     * @return GetListsResponse
     */
    public function getLists($ids = null, $args = [])
    {
        if ($ids) {
            $args['id'] = $ids;
        }

        return $this->getResult('getLists', $args, is_array($ids));
    }

    /**
     * Get a list by ID.
     *
     * @param int   $id
     * @param array $args
     *
     * @link http://developers.marketo.com/documentation/rest/get-list-by-id/
     *
     * @return GetListResponse
     */
    public function getList($id, $args = [])
    {
        $args['id'] = $id;

        return $this->getResult('getList', $args);
    }

    /**
     * Get multiple leads by filter type.
     *
     * @param string $filterType
     * @param string $filterValues
     * @param array  $args
     *
     * @link http://developers.marketo.com/documentation/rest/get-multiple-leads-by-filter-type/
     *
     * @return GetLeadsResponse
     */
    public function getLeadsByFilterType($filterType, $filterValues, $args = [])
    {
        $args['filterType'] = $filterType;
        $args['filterValues'] = $filterValues;

        return $this->getResult('getLeadsByFilterType', $args);
    }

    /**
     * Get a lead by filter type.
     *
     * Convenient method which uses {@link http://developers.marketo.com/documentation/rest/get-multiple-leads-by-filter-type/}
     * internally and just returns the first lead if there is one.
     *
     * @param string $type
     * @param string $value
     * @param array  $args
     *
     * @link http://developers.marketo.com/documentation/rest/get-multiple-leads-by-filter-type/
     *
     * @return GetLeadResponse
     */
    public function getLeadByFilterType($type, $value, $args = [])
    {
        $args['filterType'] = $type;
        $args['filterValues'] = $value;

        return $this->getResult('getLeadByFilterType', $args);
    }

    /**
     * Get multiple leads by list ID.
     *
     * @param int   $listId
     * @param array $args
     *
     * @link http://developers.marketo.com/documentation/rest/get-multiple-leads-by-list-id/
     *
     * @return GetLeadsResponse
     */
    public function getLeadsByList($listId, $args = [])
    {
        $args['listId'] = $listId;

        return $this->getResult('getLeadsByList', $args);
    }

    /**
     * Get a lead by ID.
     *
     * @param int   $id
     * @param array $args
     *
     * @link http://developers.marketo.com/documentation/rest/get-lead-by-id/
     *
     * @return GetLeadResponse
     */
    public function getLead($id, $args = [])
    {
        $args['id'] = $id;
        return $this->getResult('getLead', $args);
    }

    /**
     * Check if a lead is a member of a list.
     *
     * @param int       $listId List ID
     * @param int|array $id     Lead ID
     * @param array     $args
     *
     * @link http://developers.marketo.com/documentation/rest/member-of-list/
     *
     * @return IsMemberOfListResponse
     */
    public function isMemberOfList($listId, $id, $args = [])
    {
        $args['listId'] = $listId;
        $args['id'] = $id;

        return $this->getResult('isMemberOfList', $args, is_array($id));
    }

    /**
     * Get a campaign by ID.
     *
     * @param int   $id
     * @param array $args
     *
     * @link http://developers.marketo.com/documentation/rest/get-campaign-by-id/
     *
     * @return GetCampaignResponse
     */
    public function getCampaign($id, $args = [])
    {
        $args['id'] = $id;

        return $this->getResult('getCampaign', $args);
    }

    /**
     * Get campaigns.
     *
     * @param int|array $ids
     * @param array     $args
     *
     * @link http://developers.marketo.com/documentation/rest/get-multiple-campaigns/
     *
     * @return GetCampaignsResponse
     */
    public function getCampaigns($ids = null, $args = [])
    {
        if ($ids) {
            $args['id'] = $ids;
        }

        return $this->getResult('getCampaigns', $args, is_array($ids));
    }

    /**
     * Add one or more leads to the specified list.
     *
     * @param int       $listId List ID
     * @param int|array $leads  Either a single lead ID or an array of lead IDs
     * @param array     $args
     *
     * @link http://developers.marketo.com/documentation/rest/add-leads-to-list/
     *
     * @return AddOrRemoveLeadsToListResponse
     */
    public function addLeadsToList($listId, $leads, $args = [])
    {
        $args['listId'] = $listId;
        $args['id'] = (array) $leads;

        return $this->getResult('addLeadsToList', $args, true);
    }

    /**
     * Remove one or more leads from the specified list.
     *
     * @param int       $listId List ID
     * @param int|array $leads  Either a single lead ID or an array of lead IDs
     * @param array     $args
     *
     * @link http://developers.marketo.com/documentation/rest/remove-leads-from-list/
     *
     * @return AddOrRemoveLeadsToListResponse
     */
    public function removeLeadsFromList($listId, $leads, $args = [])
    {
        $args['listId'] = $listId;
        $args['id'] = (array) $leads;

        return $this->getResult('removeLeadsToList', $args, true);
    }

    /**
     * Internal helper method to actually perform command.
     *
     * @param string $command
     * @param array  $args
     * @param bool   $fixArgs
     *
     * @return Response
     */
    private function getResult($command, $args, $fixArgs = false)
    {
        $cmd = $this->getCommand($command, $args);

        // Marketo expects parameter arrays in the format id=1&id=2, Guzzle formats them as id[0]=1&id[1]=2.
        // Use a quick regex to fix it where necessary.
        if ($fixArgs) {
            $cmd->prepare();

            $url = preg_replace('/id%5B([0-9]+)%5D/', 'id', $cmd->getRequest()->getUrl());
            $cmd->getRequest()->setUrl($url);
        }

        return $cmd->getResult();
    }
}
