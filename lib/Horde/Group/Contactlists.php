<?php
/**
 * The Group_contactlists class provides a groups system based on Turba
 * contact lists. Only SQL sources are supported.
 *
 * Copyright 2008-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author   Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @package  Group
 */
class Horde_Group_Contactlists extends Horde_Group
{
    /**
     * A cache object
     *
     * @var Horde_Cache object
     */
    protected $_cache = null;

    /**
     * Handles for the database connections. Need one for each possible
     * source.
     *
     * @var DB
     */
    protected $_db = array();

    /**
     * Local copy of available address book sources that the group driver can
     * use.
     *
     * @var array of Turba's cfgSource style entries.
     */
    protected $_sources = array();

    /**
     * Local cache of retreived group entries from Turba storage.
     *
     * @var unknown_type
     */
    protected $_listEntries = array();

    /**
     * Constructor.
     */
    public function __construct(array $params = array())
    {
        // Get a list of all available Turba sources
        $turba_sources = Horde::loadConfiguration('sources.php',
                                                  'cfgSources', 'turba');

        // We only support sql type sources.
        foreach ($turba_sources as $key => $source) {
            if ($source['type'] == 'sql') {
                $this->_sources[$key] = $source;
            }
        }

        $this->_cache = $GLOBALS['injector']->getInstance('Horde_Cache');
    }

    /**
     * Initializes the object.
     */
    public function __wakeup()
    {
    }

    /**
     * Returns the properties that need to be serialized.
     *
     * @return array  List of serializable properties.
     */
    public function __sleep()
    {
    }

    /**
     * Stores the object in the session cache.
     */
    public function shutdown()
    {
    }

    /**
     * Returns a new group object.
     *
     * @param string $name    The group's name.
     * @param string $parent  The group's parent's name.
     *
     * @throws Horde_Group_Exception
     */
    public function newGroup($name, $parent = GROUP_ROOT)
    {
        throw new Horde_Group_Exception('Unsupported.');
    }

    /**
     * Returns a Group object corresponding to the named group,
     * with the users and other data retrieved appropriately.
     *
     * This is deprecated. Use getGroupById() instead.
     *
     * @param string $name  The name of the group to retrieve.
     * @throws Horde_Group_Exception
     */
    public function getGroup($name)
    {
        throw new Horde_Group_Exception('Deprecated. Use getGroupById() instead.');
    }

    /**
     * Returns a Horde_Group_ContactListObject object corresponding to the
     * given unique ID, with the users and other data retrieved
     * appropriately.
     *
     * @param integer $cid  The unique ID of the group to retrieve.
     *
     * @return Horde_Group_ContactListObject
     * @throws Horde_Group_Exception
     */
    public function getGroupById($gid)
    {
        if (!empty($this->_groupCache[$gid])) {
            return $this->_groupCache[$gid];
        }

        list($source, $id) = explode(':', $gid);
        $entry = $this->_retrieveListEntry($gid);
        if (empty($entry)) {
            throw new Horde_Group_Exception($gid . ' does not exist');
        }

        $users = $this->_getAllMembers($gid);

        $group = new Horde_Group_ContactListObject($entry[$this->_sources[$source]['map'][$this->_sources[$source]['list_name_field']]]);
        $group->id = $gid;
        $group->data['email'] = $entry[$this->_sources[$source]['map']['email']];
        if (!empty($users)) {
            $group->data['users'] = array_flip($users);
        }

        $group->setGroupOb($this);
        $this->_groupCache[$gid] = $group;

        return $group;
    }

    /**
     * Adds a group to the groups system. The group must first be created with
     * newGroup(), and have any initial users added to it, before this
     * function is called.
     *
     * @param Horde_Group_ContactListObject $group  The new group object.
     * @throws Horde_Group_Exception
     */
    public function addGroup($group)
    {
        throw new Horde_Group_Exception('Unsupported.');
    }

    /**
     * Stores updated data - users, etc. - of a group to the backend system.
     *
     * @param ContactListObject_Group $group  The group to update.
     *
     * @throws Horde_Group_Exception
     */
    public function updateGroup($group)
    {
        throw new Horde_Group_Exception('Unsupported.');
    }

    /**
     * Removes a group from the groups system permanently.
     *
     * @param ContactListObject_Group $group  The group to remove.
     * @param boolean $force                  Force to remove every child.
     *
     * @throws Horde_Group_Exception
     */
    public function removeGroup($group, $force = false)
    {
        throw new Horde_Group_Exception('Unsupported.');
    }

    /**
     * Retrieves the name of a group.
     *
     * @param integer|Horde_Group_ContactListObject $gid  The id of the group
     *                                                    or the group object
     *                                                    to retrieve the name
     *                                                    for.
     *
     * @return string  The group's name.
     * @throws Horde_Group_Exception
     */
    public function getGroupName($gid)
    {
        static $beenHere;

        if (strpos($gid, ':') === false) {
            throw new Horde_Group_Exception(sprintf('Group %s not found.', $gid));
        }

        if ($gid instanceof Horde_Group_ContactListObject) {
            $gid = $gid->getId();
        }

        if (!empty($this->_listEntries[$gid])) {
            list($source, $id) = explode(':', $gid);
            $beenHere = false;
            return $this->_listEntries[$gid][$this->_sources[$source]['map'][$this->_sources[$source]['list_name_field']]];
        }

        $this->_retrieveListEntry($gid);

        // We should have the information cached now, try again..but protect
        // against anything nasty...
        if (!$beenHere) {
            $beenHere = true;
            return $this->getGroupName($gid);
        }

        throw new Horde_Group_Exception(sprintf('Group %s not found.', $gid));
    }

    /**
     * Strips all parent references off of the given group name.
     * Not used in this driver...group display names are ONLY for display.
     *
     * @param string $group  Name of the group.
     *
     * @return string  The name of the group without parents.
     */
    public function getGroupShortName($group)
    {
       return $group;
    }

    /**
     * Retrieves the ID of a group, given the group object.
     * Here for BC. Kinda silly, since if we have the object, we can just call
     * getId() ourselves.
     *
     * @param ContactListObject_Group $group  The group object to retrieve the
     *                                        ID for.
     *
     * @return integer  The group's ID.
     * @throws Horde_Group_Exception
     */
    public function getGroupId($group)
    {
        if ($group instanceof Horde_Group_ContactListObject) {
            return $group->getId();
        }

        throw new Horde_Group_Exception('Unsupported.');
    }

    /**
     * Check if a group exists in the system.
     * This must either be a noop or we need to somehow "uniqueify" the
     * list's display name?
     *
     * @param string $group  The group name to check.
     *
     * @return boolean  True if the group exists, false otherwise.
     */
    public function exists($group)
    {
        return true;
    }

    /**
     * Returns a tree of the parents of a child group.
     *
     * @param integer $gid  The id of the child group.
     *
     * @return array  The group parents tree, with groupnames as the keys.
     */
    public function getGroupParents($gid)
    {
        return array();
    }

    /**
     * Returns the single parent ID of the given group.
     *
     * @param integer $gid  The ID of the child group.
     *
     * @return integer  The parent of the given group.
     */
    public function getGroupParent($gid)
    {
        return self::ROOT;
    }

    /**
     * Returns a flat list of the parents of a child group
     *
     * @param integer $gid  The id of the group.
     *
     * @return array  A flat list of all of the parents of $group, hashed in
     *                $id => $name format.
     */
    public function getGroupParentList($gid)
    {
        return array();
    }

    /**
     * Returns a list of all groups, in the format id => groupname.
     * The groups returned represent only the groups visible to the current
     * user only.
     *
     * @param boolean $refresh  If true, the cached value is ignored and the
     *                          group list is refreshed from the group backend.
     *
     * @return array  ID => groupname hash.
     */
    public function listGroups($refresh = false)
    {
        if (isset($this->_groupList) && !$refresh) {
            return $this->_groupList;
        }

        // First, make sure we are connected to all sources
        $this->_connect();

        $groups = $owners = array();

        foreach ($this->_sources as $key => $source) {
            if ($source['use_shares']) {
                if (empty($contact_shares)) {
                    $scope = $GLOBALS['registry']->hasInterface('contacts');
                    $shares = $GLOBALS['injector']->getInstance('Horde_Share')->getScope($scope);
                    $this->_contact_shares = $shares->listShares($GLOBALS['registry']->getAuth(), Horde_Perms::SHOW, $GLOBALS['registry']->getAuth());
                }
                // Contruct a list of owner ids to use
                foreach ($this->_contact_shares as $id => $share) {
                    $params = @unserialize($share->get('params'));
                    if ($params['source'] == $key) {
                        $owners[] = $params['name'];
                    }
                }
            } else {
                $owners = array($GLOBALS['registry']->getAuth());
            }
            $owner_ids = array();
            foreach ($owners as $owner) {
                $owner_ids[] = $this->_db[$key]->quote($owner);
            }
            $sql = 'SELECT ' . $source['map']['__key'] . ', ' . $source['map'][$source['list_name_field']]
                . '  FROM ' . $source['params']['table'] . ' WHERE '
                . $source['map']['__type'] . ' = \'Group\' AND '
                . $source['map']['__owner'] . ' IN (' . implode(',', $owner_ids ) . ')';

           $results = $this->_db[$key]->getAssoc($sql);
           foreach ($results as $id => $name) {
               $groups[$key . ':' . $id] = $name;
           }
        }
        $this->_groupList = $groups;

        return $this->_groupList;
    }

    /**
     * Get a list of every user that is part of the specified group
     * and any of its subgroups.
     *
     * @param integer $group  The ID of the parent group.
     *
     * @return array  The complete user list.
     * @throws Horde_Group_Exception
     */
    public function listAllUsers($gid)
    {
        return array_values($this->_getAllMembers($gid, true));
    }

    /**
     * Returns a hash representing the list entry. Items are keyed by the
     * backend specific keys.
     *
     * @param string $gid  The group id.
     *
     * @return array
     * @throws Horde_Group_Exception
     */
    protected function _retrieveListEntry($gid)
    {
        if (!empty($this->_listEntries[$gid])) {
            return $this->_listEntries[$gid];
        }

        list($source, $id) = explode(':', $gid);
        if (empty($this->_sources[$source])) {
            return array();
        }

        $this->_connect($source);
        $sql = 'SELECT ' . $this->_sources[$source]['map']['__members'] . ','
            . $this->_sources[$source]['map']['email'] . ','
            . $this->_sources[$source]['map'][$this->_sources[$source]['list_name_field']]
            . ' from ' . $this->_sources[$source]['params']['table'] . ' WHERE '
            . $this->_sources[$source]['map']['__key'] . ' = ' . $this->_db[$source]->quote($id);

        $results = $this->_db[$source]->getRow($sql, array(), DB_FETCHMODE_ASSOC);
        if ($results instanceof PEAR_Error) {
            Horde::logMessage($results, 'ERR');
            throw new Horde_Group_Exception($results);
        }
        $this->_listEntries[$gid] = $results;

        return $results;
    }

    /**
     * TODO
     *
     * @throws Horde_Group_Exception
     */
    protected function _getAllMembers($gid, $subGroups = false)
    {
        if (empty($gid) || strpos($gid, ':') === false) {
            throw new Horde_Group_Exception(sprintf('Unsupported group id: %s', $gid));
        }

        list($source, $id) = explode(':', $gid);
        $entry = $this->_retrieveListEntry($gid);
        $members = @unserialize($entry[$this->_sources[$source]['map']['__members']]);
        $users = array();

        // TODO: optimize this to only query each table once
        foreach ($members as $member) {
            // Is this member from the same source or a different one?
            if (strpos($member, ':') !== false) {
                list($newSource, $uid) = explode(':', $member);
                if (!empty($this->_contact_shares[$newSource])) {
                    $params = @unserialize($this->_contact_shares[$newSource]->get('params'));
                    $newSource = $params['source'];
                    $member = $uid;
                    $this->_connect($newSource);
                } elseif (empty($this->_sources[$newSource])) {
                    // Last chance, it's not in one of our non-share sources
                    continue;
                }
            } else {
                // Same source
                $newSource = $source;
            }

            $sql = 'SELECT ' . $this->_sources[$newSource]['map']['email']
                . ', ' . $this->_sources[$newSource]['map']['__type']
                . ' FROM ' . $this->_sources[$newSource]['params']['table']
                . ' WHERE ' . $this->_sources[$newSource]['map']['__key']
                . ' = ' . $this->_db[$newSource]->quote($member);

            $results = $this->_db[$newSource]->getRow($sql);
            if ($results instanceof PEAR_Error) {
                Horde::logMessage($results, 'ERR');
                throw new Horde_Group_Exception($results);
            }

            // Sub-Lists are treated as sub groups the best that we can...
            if ($subGroups && $results[1] == 'Group') {
                $this->_subGroups[$gid] = $newSource . ':' . $member;
                $users = array_merge($users, $this->_getAllMembers($newSource . ':' . $member));
            }
            if (strlen($results[0])) {
                // use a key to dump dups
                $users[$results[0]] = $results[0];
            }
        }

        return $users;
    }

    /**
     * Returns ALL contact lists present in ALL sources that this driver knows
     * about.
     *
     * @throws Horde_Group_Exception
     */
    protected function _listAllLists()
    {
        // Clear the cache - we will rebuild it.
        $this->_listEntries = array();

        foreach ($this->_sources as $key => $source) {
            $this->_connect($key);
            $sql = 'SELECT ' . $source['map']['__key'] . ','
            . $source['map']['__members'] . ','
            . $source['map']['email'] . ','
            . $source['map'][$source['list_name_field']]
            . ' FROM ' . $source['params']['table'] . ' WHERE '
            . $source['map']['__type'] . ' = \'Group\'';

           $results = $this->_db[$key]->query($sql);
           if ($results instanceof PEAR_Error) {
               throw new Horde_Group_Exception($results);
           }

           while ($row = $results->fetchRow(DB_FETCHMODE_ASSOC)) {
                $this->_listEntries[$key . ':' . $row[$source['map']['__key']]] = $row;
           }
        }

        return $this->_listEntries;
    }

    /**
     * Get a list of every group that $user is in.
     *
     * @param string  $user          The user to get groups for.
     * @param boolean $parentGroups  Also return the parents of any groups?
     *
     * @return array  An array of all groups the user is in.
     */
    public function getGroupMemberships($user, $parentGroups = false)
    {
        if (($memberships = $this->_cache->get('Group_contactlists_memberships' . md5($user))) !== false) {
            return unserialize($memberships);
        }
        $lists = $this->_listAllLists();
        $memberships = array();
        foreach (array_keys($lists) as $list) {
            $members = $this->_getAllMembers($list, $parentGroups);
            if (!empty($members[$user])) {
                $memberships[] = $list;
            }
        }

        $this->_cache->set('Group_contactlists_memberships' . md5($user), serialize($memberships));

        return $memberships;
    }

    /**
     * Say if a user is a member of a group or not.
     *
     * @param string $user        The name of the user.
     * @param integer $gid        The ID of the group.
     * @param boolean $subgroups  Return true if the user is in any subgroups
     *                            of group with ID $gid, also.
     *
     * @return boolean
     */
    public function userIsInGroup($user, $gid, $subgroups = true)
    {
        if (isset($_SESSION['horde']['groups']['i'][$user][$subgroups][$gid])) {
            return $_SESSION['horde']['groups']['i'][$user][$subgroups][$gid];
        }

        try {
            $users = $this->_getAllMembers($gid, $subgroups);
        } catch (Horde_Group_Exception $e) {
            Horde::logMessage($e, 'ERR');
            return false;
        }

        $result = (bool)!empty($users[$user]);
        $_SESSION['horde']['groups']['i'][$user][$subgroups][$gid] = $result;

        return $result;
    }

    /**
     * Attempts to open a persistent connection to the sql server.
     *
     * @return boolean  True on success.
     * @throws Horde_Group_Exception
     */
    protected function _connect($source = null)
    {
        if (!is_null($source) && !empty($this->_db[$source])) {
            return;
        }

        $sources = is_null($source)
            ? array_keys($this->_sources)
            : array($source);

        foreach ($sources as $source) {
            if (empty($this->_db[$source])) {
                $this->_db[$source] = DB::connect($this->_sources[$source]['params'],
                    array('persistent' => !empty($this->_sources[$source]['params']['persistent'])));
                if ($this->_db[$source] instanceof PEAR_Error) {
                    throw new Horde_Group_Exception($this->_db[$source]);
                }

                /* Set DB portability options. */
                switch ($this->_db[$source]->phptype) {
                case 'mssql':
                    $this->_db[$source]->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS | DB_PORTABILITY_RTRIM);
                    break;

                default:
                    $this->_db[$source]->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS);
                }
            }
        }

        return true;
    }

}
