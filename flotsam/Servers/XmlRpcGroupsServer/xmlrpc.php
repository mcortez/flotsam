<?php
//	ini_set("display_errors",0);
    /*
      Actual failures that result in mysql or php errors should be returned as:
      
      return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
      
      Methods that run without errors, but do not have the intended result should return as:
      
      return array('succeed' => 'false', 'message' => 'No Groups Found', 'params' => var_export($params, TRUE));
      
      or if applicable:
      
      return array('succeed' => 'false', 'message' => 'What went wrong', 'params' => var_export($params, TRUE), 'sql' => $sql);

      
      
    */

    include("phpxmlrpclib/xmlrpc.inc");
    include("phpxmlrpclib/xmlrpcs.inc");

	$membersVisibleTo = 'Owners';
    include("config.php");
	

	$groupPowers = array(
        'None' => '0',
        /// <summary>Can send invitations to groups default role</summary>
        'Invite' => '2',
        /// <summary>Can eject members from group</summary>
        'Eject' => '4',
        /// <summary>Can toggle 'Open Enrollment' and change 'Signup fee'</summary>
        'ChangeOptions' => '8',
        /// <summary>Can create new roles</summary>
        'CreateRole' => '16',
        /// <summary>Can delete existing roles</summary>
        'DeleteRole' => '32',
        /// <summary>Can change Role names, titles and descriptions</summary>
        'RoleProperties' => '64',
        /// <summary>Can assign other members to assigners role</summary>
        'AssignMemberLimited' => '128',
        /// <summary>Can assign other members to any role</summary>
        'AssignMember' => '256',
        /// <summary>Can remove members from roles</summary>
        'RemoveMember' => '512',
        /// <summary>Can assign and remove abilities in roles</summary>
        'ChangeActions' => '1024',
        /// <summary>Can change group Charter, Insignia, 'Publish on the web' and which
        /// members are publicly visible in group member listings</summary>
        'ChangeIdentity' => '2048',
        /// <summary>Can buy land or deed land to group</summary>
        'LandDeed' => '4096',
        /// <summary>Can abandon group owned land to Governor Linden on mainland, or Estate owner for
        /// private estates</summary>
        'LandRelease' => '8192',
        /// <summary>Can set land for-sale information on group owned parcels</summary>
        'LandSetSale' => '16384',
        /// <summary>Can subdivide and join parcels</summary>
        'LandDivideJoin' => '32768',
        /// <summary>Can join group chat sessions</summary>
        'JoinChat' => '65536',
        /// <summary>Can toggle "Show in Find Places" and set search category</summary>
        'FindPlaces' => '131072',
        /// <summary>Can change parcel name, description, and 'Publish on web' settings</summary>
        'LandChangeIdentity' => '262144',
        /// <summary>Can set the landing point and teleport routing on group land</summary>
        'SetLandingPoint' => '524288',
        /// <summary>Can change music and media settings</summary>
        'ChangeMedia' => '1048576',
        /// <summary>Can toggle 'Edit Terrain' option in Land settings</summary>
        'LandEdit' => '2097152',
        /// <summary>Can toggle various About Land > Options settings</summary>
        'LandOptions' => '4194304',
        /// <summary>Can always terraform land, even if parcel settings have it turned off</summary>
        'AllowEditLand' => '8388608',
        /// <summary>Can always fly while over group owned land</summary>
        'AllowFly' => '16777216',
        /// <summary>Can always rez objects on group owned land</summary>
        'AllowRez' => '33554432',
        /// <summary>Can always create landmarks for group owned parcels</summary>
        'AllowLandmark' => '67108864',
        /// <summary>Can use voice chat in Group Chat sessions</summary>
        'AllowVoiceChat' => '134217728',
        /// <summary>Can set home location on any group owned parcel</summary>
        'AllowSetHome' => '268435456',
        /// <summary>Can modify public access settings for group owned parcels</summary>
        'LandManageAllowed' => '536870912',
        /// <summary>Can manager parcel ban lists on group owned land</summary>
        'LandManageBanned' => '1073741824',
        /// <summary>Can manage pass list sales information</summary>
        'LandManagePasses' => '2147483648',
        /// <summary>Can eject and freeze other avatars on group owned land</summary>
        'LandEjectAndFreeze' => '4294967296',
        /// <summary>Can return objects set to group</summary>
        'ReturnGroupSet' => '8589934592',
        /// <summary>Can return non-group owned/set objects</summary>
        'ReturnNonGroup' => '17179869184',
        /// <summary>Can landscape using Linden plants</summary>
        'LandGardening' => '34359738368',
        /// <summary>Can deed objects to group</summary>
        'DeedObject' => '68719476736',
        /// <summary>Can moderate group chat sessions</summary>
        'ModerateChat' => '137438953472',
        /// <summary>Can move group owned objects</summary>
        'ObjectManipulate' => '274877906944',
        /// <summary>Can set group owned objects for-sale</summary>
        'ObjectSetForSale' => '549755813888',
        /// <summary>Pay group liabilities and receive group dividends</summary>
        'Accountable' => '1099511627776',
        /// <summary>Can send group notices</summary>
        'SendNotices'    => '4398046511104',
        /// <summary>Can receive group notices</summary>
        'ReceiveNotices' => '8796093022208',
        /// <summary>Can create group proposals</summary>
        'StartProposal' => '17592186044416',
        /// <summary>Can vote on group proposals</summary>
        'VoteOnProposal' => '35184372088832',
        /// <summary>Can return group owned objects</summary>
        'ReturnGroupOwned' => '281474976710656'
		
		
        /// <summary>Members are visible to non-owners</summary>
		, 'RoleMembersVisible' => '140737488355328'
		);
	
	
	
    $uuidZero = "00000000-0000-0000-0000-000000000000";
    
    $groupDBCon = mysql_connect($dbHost,$dbUser,$dbPassword);
    if (!$groupDBCon)
    {
        die('Could not connect: ' . mysql_error());
    }
    mysql_select_db($dbName, $groupDBCon);

	// This is filled in by secure()
	$requestingAgent = $uuidZero;

    
    function test() 
    {
        return array('name' => 'Joe','age' => 27);
    }

    // Use a common signature for all the group functions  ->  struct foo($struct)
    $common_sig = array(array($xmlrpcStruct, $xmlrpcStruct));

    function createGroup($params)
    {
		if( is_array($error = secureRequest($params, TRUE)) )
		{
			return $error;
		}
	
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon;
        $groupID = $params["GroupID"];
        $name = addslashes( $params["Name"] );
        $charter = addslashes( $params["Charter"] );
        $insigniaID = $params["InsigniaID"];
        $founderID = $params["FounderID"];
        $membershipFee = $params["MembershipFee"];
        $openEnrollment = $params["OpenEnrollment"];
        $showInList = $params["ShowInList"];
        $allowPublish = $params["AllowPublish"];
        $maturePublish = $params["MaturePublish"];
        $ownerRoleID = $params["OwnerRoleID"];
        $everyonePowers = $params["EveryonePowers"];
        $ownersPowers = $params["OwnersPowers"];
        
        // Create group
        $sql = "INSERT INTO osgroup
                (GroupID, Name, Charter, InsigniaID, FounderID, MembershipFee, OpenEnrollment, ShowInList, AllowPublish, MaturePublish, OwnerRoleID)
                VALUES
                ('$groupID', '$name', '$charter', '$insigniaID', '$founderID', $membershipFee, $openEnrollment, $showInList, $allowPublish, $maturePublish, '$ownerRoleID')";
        
        if (!mysql_query($sql, $groupDBCon))
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        // Create Everyone Role
		// NOTE: FIXME: This is a temp fix until the libomv enum for group powers is fixed in OpenSim
		
        $result = _addRoleToGroup(array('GroupID' => $groupID, 'RoleID' => $uuidZero, 'Name' => 'Everyone', 'Description' => 'Everyone in the group is in the everyone role.', 'Title' => "Member of $name", 'Powers' => $everyonePowers));
        if( isset($result['error']) )
        {
            return $result;
        }

        // Create Owner Role
        $result = _addRoleToGroup(array('GroupID' => $groupID, 'RoleID' => $ownerRoleID, 'Name' => 'Owners', 'Description' => "Owners of $name", 'Title' => "Owner of $name", 'Powers' => $ownersPowers));
        if( isset($result['error']) )
        {
            return $result;
        }

        // Add founder to group, will automatically place them in the Everyone Role, also places them in specified Owner Role
        $result = _addAgentToGroup(array('AgentID' => $founderID, 'GroupID' => $groupID, 'RoleID' => $ownerRoleID));
        if( isset($result['error']) )
        {
            return $result;
        }
        
        
        // Select the owner's role for the founder
        $result = _setAgentGroupSelectedRole(array('AgentID' => $founderID, 'RoleID' => $ownerRoleID, 'GroupID' => $groupID));
        if( isset($result['error']) )
        {
            return $result;
        }
        
        // Set the new group as the founder's active group
        $result = _setAgentActiveGroup(array('AgentID' => $founderID, 'GroupID' => $groupID));
        if( isset($result['error']) )
        {
            return $result;
        }
        
        
        return getGroup(array("GroupID"=>$groupID));
    }
    
	// Private method, does not include security, to only be called from places that have already verified security
    function _addRoleToGroup($params)
    {
		$everyonePowers = 8796495740928; // This should now be fixed, when libomv was updated...		
	
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon, $groupPowers;
        $groupID = $params['GroupID'];
        $roleID  = $params['RoleID'];
        $name    = addslashes( $params['Name'] );
        $desc    = addslashes( $params['Description'] );
        $title   = addslashes( $params['Title'] );
        $powers  = $params['Powers'];

		
		if( !isset($powers) || ($powers == 0) || ($powers == '') )
		{
			$powers = $everyonePowers;
		}
        
        $sql = " INSERT INTO osrole (GroupID, RoleID, Name, Description, Title, Powers) VALUES "
              ." ('$groupID', '$roleID', '$name', '$desc', '$title', $powers)";

        if (!mysql_query($sql, $groupDBCon))
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error()
                       , 'method' => 'addRoleToGroup'
                       , 'params' => var_export($params, TRUE));
        }
        
        return array("success" => "true");
    }
	
	
    function addRoleToGroup($params)
    {
		if( is_array($error = secureRequest($params, TRUE)) )
		{
			return $error;
		}
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon, $groupPowers;
        $groupID = $params['GroupID'];
        
		// Verify the requesting agent has permission
		if( is_array($error = checkGroupPermission($groupID, $groupPowers['CreateRole'])) )
		{
			return $error;
		}

		return _addRoleToGroup($params);
    }
    
    function updateGroupRole($params)
    {
		if( is_array($error = secureRequest($params, TRUE)) )
		{
			return $error;
		}
		
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon, $groupPowers;
        $groupID = $params['GroupID'];
        $roleID  = $params['RoleID'];
        $name    = addslashes( $params['Name'] );
        $desc    = addslashes( $params['Description'] );
        $title   = addslashes( $params['Title'] );
        $powers  = $params['Powers'];
        
		// Verify the requesting agent has permission
		if( is_array($error = checkGroupPermission($groupID, $groupPowers['RoleProperties'])) )
		{
			return $error;
		}
		
		
        $sql = " UPDATE osrole SET RoleID = '$roleID' ";
        if( isset($params['Name']) )
        {
            $sql .= ", Name = '$name'";
        }
        if( isset($params['Description']) )
        {
            $sql .= ", Description = '$desc'";
        }
        if( isset($params['Title']) )
        {
            $sql .= ", Title = '$title'";
        }
        if( isset($params['Powers']) )
        {
            $sql .= ", Powers = $powers";
        }
        
        $sql .= " WHERE GroupID = '$groupID' AND RoleID = '$roleID'";

        if (!mysql_query($sql, $groupDBCon))
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        return array("success" => "true");
    }
    
    function removeRoleFromGroup($params)
    {
		if( is_array($error = secureRequest($params, TRUE)) )
		{
			return $error;
		}
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon, $groupPowers;
        $groupID = $params['GroupID'];
        $roleID  = $params['RoleID'];
        
		if( is_array($error = checkGroupPermission($groupID, $groupPowers['RoleProperties'])) )
		{
			return $error;
		}
		
        /// 1. Remove all members from Role
        /// 2. Set selected Role to uuidZero for anyone that had the role selected
        /// 3. Delete roll
        
        $sql = "DELETE FROM osgrouprolemembership WHERE GroupID = '$groupID' AND RoleID = '$roleID'";
        if (!mysql_query($sql, $groupDBCon))
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        $sql = "UPDATE osgroupmembership SET SelectedRoleID = '$uuidZero' WHERE GroupID = '$groupID' AND SelectedRoleID = '$roleID'";
        if (!mysql_query($sql, $groupDBCon))
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        $sql = "DELETE FROM osrole WHERE GroupID = '$groupID' AND RoleID = '$roleID'";
        if (!mysql_query($sql, $groupDBCon))
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        return array("success" => "true");
    }
    

    
    function getGroup($params)
    {
		if( is_array($error = secureRequest($params, FALSE)) )
		{
			return $error;
		}
		
		return _getGroup($params);
	}
		
    function _getGroup($params)
    {
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon;
        $sql = " SELECT osgroup.GroupID, osgroup.Name, Charter, InsigniaID, FounderID, MembershipFee, OpenEnrollment, ShowInList, AllowPublish, MaturePublish, OwnerRoleID"
              ." , count(osrole.RoleID) as GroupRolesCount, count(osgroupmembership.AgentID) as GroupMembershipCount "
              ." FROM osgroup "
              ." LEFT JOIN osrole ON (osgroup.GroupID = osrole.GroupID)"
              ." LEFT JOIN osgroupmembership ON (osgroup.GroupID = osgroupmembership.GroupID)"
              ." WHERE ";
        if( isset($params['GroupID']) )
        {
            $sql .= "osgroup.GroupID = '".$params['GroupID']."'";
            
        } else if( isset($params['Name']) ) 
        {
            $sql .= "osgroup.Name = '".addslashes($params['Name'])."'";
        } else {
            return array("error" => "Must specify GroupID or Name");
        }
        
        $sql .= " GROUP BY osgroup.GroupID, osgroup.name, charter, insigniaID, founderID, membershipFee, openEnrollment, showInList, allowPublish, maturePublish, ownerRoleID";
        
        $result = mysql_query($sql, $groupDBCon);
        
        if (!$result) 
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }

        if (mysql_num_rows($result) == 0) 
        {
            return array('succeed' => 'false', 'error' => 'Group Not Found', 'params' => var_export($params, TRUE), 'sql' => $sql);
        }
        
        return mysql_fetch_assoc($result);
        
    }        
    
    function updateGroup($params)
    {
		if( is_array($error = secureRequest($params, TRUE)) )
		{
			return $error;
		}
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon, $groupPowers;
        $groupID = $params["GroupID"];
        $charter = addslashes( $params["Charter"] );
        $insigniaID = $params["InsigniaID"];
        $membershipFee = $params["MembershipFee"];
        $openEnrollment = $params["OpenEnrollment"];
        $showInList = $params["ShowInList"];
        $allowPublish = $params["AllowPublish"];
        $maturePublish = $params["MaturePublish"];
        
		if( is_array($error = checkGroupPermission($groupID, $groupPowers['ChangeOptions'])) )
		{
			return $error;
		}
		
        // Create group
        $sql = "UPDATE osgroup
                SET
                    Charter = '$charter'
                    , InsigniaID = '$insigniaID'
                    , MembershipFee = $membershipFee
                    , OpenEnrollment= $openEnrollment
                    , ShowInList    = $showInList
                    , AllowPublish  = $allowPublish
                    , MaturePublish = $maturePublish
                WHERE
                    GroupID = '$groupID'";
        
        if (!mysql_query($sql, $groupDBCon))
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }

        return array('success' => 'true');
    }

    
    function findGroups($params)
    {
		if( is_array($error = secureRequest($params, FALSE)) )
		{
			return $error;
		}
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon;
        $search = addslashes( $params['Search'] );
        
        $sql = " SELECT osgroup.GroupID, osgroup.Name, count(osgroupmembership.AgentID) as Members "
              ." FROM osgroup LEFT JOIN osgroupmembership ON (osgroup.GroupID = osgroupmembership.GroupID) "
              ." WHERE "
			  ." (    MATCH (osgroup.name) AGAINST ('$search' IN BOOLEAN MODE)"
              ."   OR osgroup.name LIKE '%$search%'"
              ."   OR osgroup.name REGEXP '$search'"
			  ." ) AND ShowInList = 1" 
              ." GROUP BY osgroup.GroupID, osgroup.Name";
        
        $result = mysql_query($sql, $groupDBCon);
        
        if (!$result) 
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }

        if( mysql_num_rows($result) == 0 )
        {
            return array('succeed' => 'false', 'error' => 'No groups found.', 'params' => var_export($params, TRUE), 'sql' => $sql);
        }
        
        $results = array();

        while ($row = mysql_fetch_assoc($result)) 
        {
            $groupID = $row['GroupID'];
            $results[$groupID] = $row;
        }
        
        return array('results' => $results, 'success' => TRUE);
        
    }
    
    function _setAgentActiveGroup($params)
    {
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon;
		$agentID = $params['AgentID'];
		$groupID = $params['GroupID'];
		
        $sql = " UPDATE osagent "
              ." SET ActiveGroupID = '$groupID'"
              ." WHERE AgentID = '$agentID'";
    
        if (!mysql_query($sql, $groupDBCon))
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        if( mysql_affected_rows() == 0 )
        {
            $sql = " INSERT INTO osagent (ActiveGroupID, AgentID) VALUES "
                  ." ('$groupID', '$agentID')";
        
            if (!mysql_query($sql, $groupDBCon))
            {
                return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
            }
        }
    
        return array("success" => "true");
    }

    function setAgentActiveGroup($params)
    {
		if( is_array($error = secureRequest($params, TRUE)) )
		{
			return $error;
		}
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon;
		$agentID = $params['AgentID'];
		$groupID = $params['GroupID'];
		
		if( isset($requestingAgent) && ($requestingAgent != $uuidZero) && ($requestingAgent != $agentID) )
		{
            return array('error' => "Agent can only change their own Selected Group Role", 'params' => var_export($params, TRUE));
		}
		
		return _setAgentActiveGroup($params);
    }
    
    function addAgentToGroup($params)
    {
		if( is_array($error = secureRequest($params, TRUE)) )
		{
			return $error;
		}
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon, $groupPowers;
        $groupID = $params["GroupID"];
        $agentID = $params["AgentID"];
		
		if( is_array($error = checkGroupPermission($groupID, $groupPowers['AssignMember'])) )
		{
			// If they don't have direct permission, check to see if the group is marked for open enrollment
			$groupInfo = _getGroup( array ('GroupID'=>$groupID) );
			
			if( isset($groupInfo['error']))
			{
				return $groupInfo;
			}

			if($groupInfo['OpenEnrollment'] != 1)
			{
				// Group is not open enrollment, check if the specified agentid has an invite
		        $sql = " SELECT GroupID, RoleID, AgentID FROM osgroupinvite"
		              ." WHERE osgroupinvite.AgentID = '$agentID' AND osgroupinvite.GroupID = '$groupID'";
		              
		        $results = mysql_query($sql, $groupDBCon);
		        if (!$results) 
		        {
		            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
		        }
		        
		        if( mysql_num_rows($results) == 1 )
		        {
					// if there is an invite, make sure we're adding the user to the role specified in the invite
		            $inviteInfo = mysql_fetch_assoc($results);
					$params['RoleID'] = $inviteInfo['RoleID'];
		        } else {
					// Not openenrollment, not invited, return permission denied error
					return $error;
				}
				
			} 
		}

		return _addAgentToGroup($params);
    }
    
	// Private method, does not include security, to only be called from places that have already verified security
    function _addAgentToGroup($params)
    {
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon;
        $agentID = $params["AgentID"];
        $groupID = $params["GroupID"];
        
        $roleID  = $uuidZero;
        if( isset($params["RoleID"]) )
        {
            $roleID = $params["RoleID"];
        }
    
        // Check if agent already a member
        $sql = " SELECT count(AgentID) as isMember FROM osgroupmembership WHERE AgentID = '$agentID' AND GroupID = '$groupID'";
        $result = mysql_query($sql, $groupDBCon);
        if (!$result)
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }

        // If not a member, add membership, select role (defaults to uuidZero, or everyone role)
        if( mysql_result($result, 0) == 0 )
        {
            $sql = " INSERT INTO osgroupmembership (GroupID, AgentID, Contribution, ListInProfile, AcceptNotices, SelectedRoleID) VALUES "
                  ."('$groupID','$agentID', 0, 1, 1,'$roleID')";
        
        
            if (!mysql_query($sql, $groupDBCon))
            {
                return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
            }
        }
        
        // Make sure they're in the Everyone role
        $result = _addAgentToGroupRole(array("GroupID" => $groupID, "RoleID" => $uuidZero, "AgentID" => $agentID));
        if( isset($result['error']) )
        {
            return $result;
        }
        
        // Make sure they're in specified role, if they were invited
        if( $roleID != $uuidZero )
        {
            $result = _addAgentToGroupRole(array("GroupID" => $groupID, "RoleID" => $roleID, "AgentID" => $agentID));
            if( isset($result['error']) )
            {
                return $result;
            }
        }

		//Set the role they were invited to as their selected role
        _setAgentGroupSelectedRole(array('AgentID' => $agentID, 'RoleID' => $roleID, 'GroupID' => $groupID));
		
		// Set the group as their active group.
		// _setAgentActiveGroup(array("GroupID" => $groupID, "AgentID" => $agentID));
		
        return array("success" => "true");
    }
    
    function removeAgentFromGroup($params)
    {
		if( is_array($error = secureRequest($params, TRUE)) )
		{
			return $error;
		}
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon, $groupPowers;
        $agentID = $params["AgentID"];
        $groupID = $params["GroupID"];

		// An agent is always allowed to remove themselves from a group -- so only check if the requesting agent is different then the agent being removed.
		if( $agentID != $requestingAgent )
		{
			if( is_array($error = checkGroupPermission($groupID, $groupPowers['RemoveMember'])) )
			{
				return $error;
			}
		}
		
        // 1. If group is agent's active group, change active group to uuidZero
        // 2. Remove Agent from group (osgroupmembership)
        // 3. Remove Agent from all of the groups roles (osgrouprolemembership)
        
        $sql = " UPDATE osagent "
              ." SET ActiveGroupID = '$uuidZero'"
              ." WHERE AgentID = '$agentID' AND ActiveGroupID = '$groupID'";
        if (!mysql_query($sql, $groupDBCon))
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        $sql = " DELETE FROM osgroupmembership "
              ." WHERE AgentID = '$agentID' AND GroupID = '$groupID'";
        if (!mysql_query($sql, $groupDBCon))
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        $sql = " DELETE FROM osgrouprolemembership "
              ." WHERE AgentID = '$agentID' AND GroupID = '$groupID'";
        if (!mysql_query($sql, $groupDBCon))
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        return array("success" => "true");
    }
    
    function _addAgentToGroupRole($params)
    {
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon;
        $agentID = $params["AgentID"];
        $groupID = $params["GroupID"];
        $roleID = $params["RoleID"];
    
        // Check if agent already a member
        $sql = " SELECT count(AgentID) as isMember FROM osgrouprolemembership WHERE AgentID = '$agentID' AND RoleID = '$roleID' AND GroupID = '$groupID'";
        $result = mysql_query($sql, $groupDBCon);
        if (!$result)
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
    
        if( mysql_result($result, 0) == 0 )
        {
            $sql = " INSERT INTO osgrouprolemembership (GroupID, RoleID, AgentID) VALUES "
                  ."('$groupID', '$roleID', '$agentID')";
        
            if (!mysql_query($sql, $groupDBCon))
            {
                return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
            }
        }
    
        return array("success" => "true");
    }
    
    function addAgentToGroupRole($params)
    {
		if( is_array($error = secureRequest($params, TRUE)) )
		{
			return $error;
		}
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon, $groupPowers;
        $agentID = $params["AgentID"];
        $groupID = $params["GroupID"];
        $roleID = $params["RoleID"];
    
		// Check if being assigned to Owners role, assignments to an owners role can only be requested by owners.
		$sql = " SELECT OwnerRoleID, osgrouprolemembership.AgentID "
		      ." FROM osgroup LEFT JOIN osgrouprolemembership ON (osgroup.GroupID = osgrouprolemembership.GroupID AND osgroup.OwnerRoleID = osgrouprolemembership.RoleID) "
			  ." WHERE osgrouprolemembership.AgentID = '$requestingAgent' AND osgroup.GroupID = '$groupID'";
			  
		$results = mysql_query($sql, $groupDBCon);
		if (!$results) 
		{
			return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
		}
		
        if( mysql_num_rows($results) == 0 )
        {
			return array('error' => "Group ($groupID) not found or Agent ($agentID) is not in the owner's role", 'params' => var_export($params, TRUE));
		}

		$ownerRoleInfo = mysql_fetch_assoc($results);
		if( ($ownerRoleInfo['OwnerRoleID'] == $roleID) && ($ownerRoleInfo['AgentID'] != $requestingAgent) )
		{
			return array('error' => "Requesting agent $requestingAgent is not a member of the Owners Role and cannot add members to the owners role.", 'params' => var_export($params, TRUE));
		}
	
	
		if( is_array($error = checkGroupPermission($groupID, $groupPowers['AssignMember'])) )
		{
			return $error;
		}
	
		return _addAgentToGroupRole($params);
    }
    
    function removeAgentFromGroupRole($params)
    {
		if( is_array($error = secureRequest($params, TRUE)) )
		{
			return $error;
		}
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon, $groupPowers;
        $agentID = $params["AgentID"];
        $groupID = $params["GroupID"];
        $roleID  = $params["RoleID"];

		if( is_array($error = checkGroupPermission($groupID, $groupPowers['AssignMember'])) )
		{
			return $error;
		}
		
        // If agent has this role selected, change their selection to everyone (uuidZero) role
        $sql = " UPDATE osgroupmembership SET SelectedRoleID = '$uuidZero' WHERE AgentID = '$agentID' AND GroupID = '$groupID' AND SelectedRoleID = '$roleID'";
        $result = mysql_query($sql, $groupDBCon);
        if (!$result)
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }

        $sql = " DELETE FROM osgrouprolemembership WHERE AgentID = '$agentID' AND GroupID = '$groupID' AND RoleID = '$roleID'";
    
        if (!mysql_query($sql, $groupDBCon))
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        return array("success" => "true");
    }
    
    function _setAgentGroupSelectedRole($params)
    {
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon;
        $agentID = $params["AgentID"];
        $groupID = $params["GroupID"];
        $roleID = $params["RoleID"];
    
        $sql = " UPDATE osgroupmembership SET SelectedRoleID = '$roleID' WHERE AgentID = '$agentID' AND GroupID = '$groupID'";
        $result = mysql_query($sql, $groupDBCon);
        if (!$result)
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
    
        return array('success' => 'true');
    }

    function setAgentGroupSelectedRole($params)
    {
		if( is_array($error = secureRequest($params, TRUE)) )
		{
			return $error;
		}
		
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon;
        $agentID = $params["AgentID"];
        $groupID = $params["GroupID"];
        $roleID = $params["RoleID"];
    
		if( isset($requestingAgent) && ($requestingAgent != $uuidZero) && ($requestingAgent != $agentID) )
		{
            return array('error' => "Agent can only change their own Selected Group Role", 'params' => var_export($params, TRUE));
		}
	
        return _setAgentGroupSelectedRole($params);
    }
	
    function getAgentGroupMembership($params)
    {
		if( is_array($error = secureRequest($params, FALSE)) )
		{
			return $error;
		}
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon;
        $groupID = $params['GroupID'];
        $agentID = $params['AgentID'];
        
        $sql = " SELECT osgroup.GroupID, osgroup.Name as GroupName, osgroup.Charter, osgroup.InsigniaID, osgroup.FounderID, osgroup.MembershipFee, osgroup.OpenEnrollment, osgroup.ShowInList, osgroup.AllowPublish, osgroup.MaturePublish"
              ." , osgroupmembership.Contribution, osgroupmembership.ListInProfile, osgroupmembership.AcceptNotices"
              ." , osgroupmembership.SelectedRoleID, osrole.Title"
              ." , osagent.ActiveGroupID "
              ." FROM osgroup JOIN osgroupmembership ON (osgroup.GroupID = osgroupmembership.GroupID)"
              ."              JOIN osrole ON (osgroupmembership.SelectedRoleID = osrole.RoleID AND osgroupmembership.GroupID = osrole.GroupID)"
              ."              JOIN osagent ON (osagent.AgentID = osgroupmembership.AgentID)"
              ." WHERE osgroup.GroupID = '$groupID' AND osgroupmembership.AgentID = '$agentID'";
        
        $groupmembershipResult = mysql_query($sql, $groupDBCon);
        if (!$groupmembershipResult) 
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        if( mysql_num_rows($groupmembershipResult) == 0 )
        {
            return array('succeed' => 'false', 'error' => 'None Found', 'params' => var_export($params, TRUE), 'sql' => $sql);
        }
        
        $groupMembershipInfo = mysql_fetch_assoc($groupmembershipResult);
        
        $sql = " SELECT BIT_OR(osrole.Powers) AS GroupPowers"
              ." FROM osgrouprolemembership JOIN osrole ON (osgrouprolemembership.GroupID = osrole.GroupID AND osgrouprolemembership.RoleID = osrole.RoleID)"
              ." WHERE osgrouprolemembership.GroupID = '$groupID' AND osgrouprolemembership.AgentID = '$agentID'";
        $groupPowersResult = mysql_query($sql, $groupDBCon);
        if (!$groupPowersResult) 
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        $groupPowersInfo = mysql_fetch_assoc($groupPowersResult);
        
        return array_merge($groupMembershipInfo, $groupPowersInfo);
    }

    function getAgentGroupMemberships($params)
    {
		if( is_array($error = secureRequest($params, FALSE)) )
		{
			return $error;
		}
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon;
        $agentID = $params['AgentID'];
        
        $sql = " SELECT osgroup.GroupID, osgroup.Name as GroupName, osgroup.Charter, osgroup.InsigniaID, osgroup.FounderID, osgroup.MembershipFee, osgroup.OpenEnrollment, osgroup.ShowInList, osgroup.AllowPublish, osgroup.MaturePublish"
              ." , osgroupmembership.Contribution, osgroupmembership.ListInProfile, osgroupmembership.AcceptNotices"
              ." , osgroupmembership.SelectedRoleID, osrole.Title"
              ." , IFNULL(osagent.ActiveGroupID, '$uuidZero') AS ActiveGroupID"
              ." FROM osgroup JOIN osgroupmembership ON (osgroup.GroupID = osgroupmembership.GroupID)"
              ."              JOIN osrole ON (osgroupmembership.SelectedRoleID = osrole.RoleID AND osgroupmembership.GroupID = osrole.GroupID)"
              ."         LEFT JOIN osagent ON (osagent.AgentID = osgroupmembership.AgentID)"
              ." WHERE osgroupmembership.AgentID = '$agentID'";
        
        $groupmembershipResults = mysql_query($sql, $groupDBCon);
        if (!$groupmembershipResults) 
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }

        if( mysql_num_rows($groupmembershipResults) == 0 )
        {
            return array('succeed' => 'false', 'error' => 'No Memberships', 'params' => var_export($params, TRUE), 'sql' => $sql);
            
        }
        
        $groupResults = array();
        while($groupMembershipInfo = mysql_fetch_assoc($groupmembershipResults))
        {
            $groupID = $groupMembershipInfo['GroupID'];
            $sql = " SELECT BIT_OR(osrole.Powers) AS GroupPowers"
                  ." FROM osgrouprolemembership JOIN osrole ON (osgrouprolemembership.GroupID = osrole.GroupID AND osgrouprolemembership.RoleID = osrole.RoleID)"
                  ." WHERE osgrouprolemembership.GroupID = '$groupID' AND osgrouprolemembership.AgentID = '$agentID'";
            $groupPowersResult = mysql_query($sql, $groupDBCon);
            if (!$groupPowersResult) 
            {
                return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
            }
            $groupPowersInfo = mysql_fetch_assoc($groupPowersResult);
            $groupResults[$groupID] = array_merge($groupMembershipInfo, $groupPowersInfo);
        }
        
        return $groupResults;
    }
    
	function canAgentViewRoleMembers( $agentID, $groupID, $roleID )
	{
		global $membersVisibleTo, $groupDBCon;
		
		if( $membersVisibleTo == 'All' ) 
			return true;
		
		$sql  = " SELECT CASE WHEN min(OwnerRoleMembership.AgentID) IS NOT NULL THEN 1 ELSE 0 END AS IsOwner ";
		$sql .= " FROM osgroup JOIN osgroupmembership ON (osgroup.GroupID = osgroupmembership.GroupID AND osgroupmembership.AgentID = '$agentID')";
		$sql .= "         LEFT JOIN osgrouprolemembership AS OwnerRoleMembership ON (OwnerRoleMembership.GroupID = osgroup.GroupID ";
		$sql .= "                   AND OwnerRoleMembership.RoleID  = osgroup.OwnerRoleID ";
		$sql .= "                   AND OwnerRoleMembership.AgentID = '$agentID')";
		$sql .= " WHERE osgroup.GroupID = '$groupID' GROUP BY osgroup.GroupID";	
		
        $viewMemberResults = mysql_query($sql, $groupDBCon);
        if (!$viewMemberResults)
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error());
        }
        
        if (mysql_num_rows($viewMemberResults) == 0) 
        {
			return false;
		}
		
		$viewMemberInfo = mysql_fetch_assoc($viewMemberResults);
		
		switch( $membersVisibleTo )
		{
			case 'Group':
				// if we get to here, there is at least one row, so they are a member of the group
				return true;
			case 'Owners':
			default:
				return $viewMemberInfo['IsOwner'];			
		}
		
	}
	
    function getGroupMembers($params)
    {
		if( is_array($error = secureRequest($params, FALSE)) )
		{
			return $error;
		}
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon, $groupPowers;
        $groupID = $params['GroupID'];
        
        $sql = " SELECT osgroupmembership.AgentID"
              ." , osgroupmembership.Contribution, osgroupmembership.ListInProfile, osgroupmembership.AcceptNotices"
              ." , osgroupmembership.SelectedRoleID, osrole.Title"
              ." , CASE WHEN OwnerRoleMembership.AgentID IS NOT NULL THEN 1 ELSE 0 END AS IsOwner"
              ." FROM osgroup JOIN osgroupmembership ON (osgroup.GroupID = osgroupmembership.GroupID)"
              ."              JOIN osrole ON (osgroupmembership.SelectedRoleID = osrole.RoleID AND osgroupmembership.GroupID = osrole.GroupID)"
              ."              JOIN osrole AS OwnerRole ON (osgroup.OwnerRoleID  = OwnerRole.RoleID AND osgroup.GroupID  = OwnerRole.GroupID)"
              ."         LEFT JOIN osgrouprolemembership AS OwnerRoleMembership ON (osgroup.OwnerRoleID       = OwnerRoleMembership.RoleID 
                                                                               AND (osgroup.GroupID           = OwnerRoleMembership.GroupID)
                                                                               AND (osgroupmembership.AgentID = OwnerRoleMembership.AgentID))"
              ." WHERE osgroup.GroupID = '$groupID'";
        
        $groupmemberResults = mysql_query($sql, $groupDBCon);
        if (!$groupmemberResults) 
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        if (mysql_num_rows($groupmemberResults) == 0) 
        {
            return array('succeed' => 'false', 'error' => 'No Group Members found', 'params' => var_export($params, TRUE), 'sql' => $sql);
        }
        
		$roleMembersVisibleBit = $groupPowers['RoleMembersVisible'];
		$canViewAllGroupRoleMembers = canAgentViewRoleMembers($requestingAgent, $groupID, '');
		
        $memberResults = array();
        while($memberInfo = mysql_fetch_assoc($groupmemberResults))
        {
            $agentID = $memberInfo['AgentID'];
            $sql = " SELECT BIT_OR(osrole.Powers) AS AgentPowers, ( BIT_OR(osrole.Powers) & $roleMembersVisibleBit) as MemberVisible"
                  ." FROM osgrouprolemembership JOIN osrole ON (osgrouprolemembership.GroupID = osrole.GroupID AND osgrouprolemembership.RoleID = osrole.RoleID)"
                  ." WHERE osgrouprolemembership.GroupID = '$groupID' AND osgrouprolemembership.AgentID = '$agentID'";
            $memberPowersResult = mysql_query($sql, $groupDBCon);
            if (!$memberPowersResult) 
            {
                return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
            }
            
            if (mysql_num_rows($groupmemberResults) == 0) 
            {
				if( $canViewAllGroupRoleMembers || ($memberResults[$agentID] == $requestingAgent))
				{
					$memberResults[$agentID] = array_merge($memberInfo, array('AgentPowers' => 0));
				} else {
					// if can't view all group role members and there is no Member Visible bit, then don't return this member's info
					unset($memberResults[$agentID]);
				}
            } else {
                $memberPowersInfo = mysql_fetch_assoc($memberPowersResult);
				if( $memberPowersInfo['MemberVisible'] || $canViewAllGroupRoleMembers  || ($memberResults[$agentID] == $requestingAgent))
				{
					$memberResults[$agentID] = array_merge($memberInfo, $memberPowersInfo);
				} else {
					// if can't view all group role members and there is no Member Visible bit, then don't return this member's info
					unset($memberResults[$agentID]);
				}
            }
        }
        
        return $memberResults;
    }
    
    
    function getAgentActiveMembership($params)
    {
		if( is_array($error = secureRequest($params, FALSE)) )
		{
			return $error;
		}
		
		secureRequest($params, FALSE);
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon;
        $agentID = $params['AgentID'];
        
        $sql = " SELECT osgroup.GroupID, osgroup.Name as GroupName, osgroup.Charter, osgroup.InsigniaID, osgroup.FounderID, osgroup.MembershipFee, osgroup.OpenEnrollment, osgroup.ShowInList, osgroup.AllowPublish, osgroup.MaturePublish"
              ." , osgroupmembership.Contribution, osgroupmembership.ListInProfile, osgroupmembership.AcceptNotices"
              ." , osgroupmembership.SelectedRoleID, osrole.Title"
              ." , osagent.ActiveGroupID "
              ." FROM osagent JOIN osgroup ON (osgroup.GroupID = osagent.ActiveGroupID)"
              ."              JOIN osgroupmembership ON (osgroup.GroupID = osgroupmembership.GroupID AND osagent.AgentID = osgroupmembership.AgentID)"
              ."              JOIN osrole ON (osgroupmembership.SelectedRoleID = osrole.RoleID AND osgroupmembership.GroupID = osrole.GroupID)"
              ." WHERE osagent.AgentID = '$agentID'";
        
        $groupmembershipResult = mysql_query($sql, $groupDBCon);
        if (!$groupmembershipResult) 
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        if (mysql_num_rows($groupmembershipResult) == 0) 
        {
            return array('succeed' => 'false', 'error' => 'No Active Group Specified', 'params' => var_export($params, TRUE), 'sql' => $sql);
        }
        $groupMembershipInfo = mysql_fetch_assoc($groupmembershipResult);
        
        $groupID = $groupMembershipInfo['GroupID'];
        $sql = " SELECT BIT_OR(osrole.Powers) AS GroupPowers"
              ." FROM osgrouprolemembership JOIN osrole ON (osgrouprolemembership.GroupID = osrole.GroupID AND osgrouprolemembership.RoleID = osrole.RoleID)"
              ." WHERE osgrouprolemembership.GroupID = '$groupID' AND osgrouprolemembership.AgentID = '$agentID'";
        $groupPowersResult = mysql_query($sql, $groupDBCon);
        if (!$groupPowersResult) 
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        $groupPowersInfo = mysql_fetch_assoc($groupPowersResult);
        
        return array_merge($groupMembershipInfo, $groupPowersInfo);
    }
    
    function getAgentRoles($params)
    {
		if( is_array($error = secureRequest($params, FALSE)) )
		{
			return $error;
		}
		
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon;
        $agentID = $params['AgentID'];
        
        $sql = " SELECT "
              ." osrole.RoleID, osrole.GroupID, osrole.Title, osrole.Name, osrole.Description, osrole.Powers"
              ." , CASE WHEN osgroupmembership.SelectedRoleID = osrole.RoleID THEN 1 ELSE 0 END AS Selected"
              ." FROM osgroupmembership JOIN osgrouprolemembership  ON (osgroupmembership.GroupID = osgrouprolemembership.GroupID AND osgroupmembership.AgentID = osgrouprolemembership.AgentID)"
              ."                        JOIN osrole ON ( osgrouprolemembership.RoleID = osrole.RoleID AND osgrouprolemembership.GroupID = osrole.GroupID)"
              ."                   LEFT JOIN osagent ON (osagent.AgentID = osgroupmembership.AgentID)"
              ." WHERE osgroupmembership.AgentID = '$agentID'";
              
        if( isset($params['GroupID']) )
        {
            $groupID = $params['GroupID'];
            $sql .= " AND osgroupmembership.GroupID = '$groupID'";
        }

        $roleResults = mysql_query($sql, $groupDBCon);
        if (!$roleResults) 
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }

        if( mysql_num_rows($roleResults) == 0 )
        {
            return array('succeed' => 'false', 'error' => 'None found', 'params' => var_export($params, TRUE), 'sql' => $sql);
        }
        
        $roles = array();
        while($role = mysql_fetch_assoc($roleResults))
        {
            $ID = $role['GroupID'].$role['RoleID'];
            $roles[$ID] = $role;
        }
        
        return $roles;
    }
    
    function getGroupRoles($params)
    {
		if( is_array($error = secureRequest($params, FALSE)) )
		{
			return $error;
		}
		
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon;
        $groupID = $params['GroupID'];
        
        $sql = " SELECT "
              ." osrole.RoleID, osrole.Name, osrole.Title, osrole.Description, osrole.Powers, count(osgrouprolemembership.AgentID) as Members"
              ." FROM osrole LEFT JOIN osgrouprolemembership ON (osrole.GroupID = osgrouprolemembership.GroupID AND osrole.RoleID = osgrouprolemembership.RoleID)"
              ." WHERE osrole.GroupID = '$groupID'"
              ." GROUP BY osrole.RoleID, osrole.Name, osrole.Title, osrole.Description, osrole.Powers";
              
        $roleResults = mysql_query($sql, $groupDBCon);
        if (!$roleResults) 
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        if( mysql_num_rows($roleResults) == 0 )
        {
            return array('succeed' => 'false', 'error' => 'No roles found for group', 'params' => var_export($params, TRUE), 'sql' => $sql);
        }
        
        $roles = array();
        while($role = mysql_fetch_assoc($roleResults))
        {
            $RoleID = $role['RoleID'];
            $roles[$RoleID] = $role;
        }
        
        return $roles;
    }

    function getGroupRoleMembers($params)
    {
		if( is_array($error = secureRequest($params, FALSE)) )
		{
			return $error;
		}
		
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon, $groupPowers;
        $groupID = $params['GroupID'];
		
		
		$roleMembersVisibleBit = $groupPowers['RoleMembersVisible'];
		$canViewAllGroupRoleMembers = canAgentViewRoleMembers($requestingAgent, $groupID, '');
		
        $sql = " SELECT "
              ." osrole.RoleID, osgrouprolemembership.AgentID"
			  ." , (osrole.Powers & $roleMembersVisibleBit) as MemberVisible"
              ." FROM osrole JOIN osgrouprolemembership ON (osrole.GroupID = osgrouprolemembership.GroupID AND osrole.RoleID = osgrouprolemembership.RoleID)"
              ." WHERE osrole.GroupID = '$groupID'";
              
        $memberResults = mysql_query($sql, $groupDBCon);
        if (!$memberResults) 
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        if( mysql_num_rows($memberResults) == 0 )
        {
            return array('succeed' => 'false', 'error' => 'No role memberships found for group', 'params' => var_export($params, TRUE), 'sql' => $sql);
        }		
        $members = array();
        while($member = mysql_fetch_assoc($memberResults))
        {
			if( $canViewAllGroupRoleMembers || $member['MemberVisible'] || ($member['AgentID'] == $requestingAgent) )
			{
	            $Key = $member['AgentID'] . $member['RoleID'];
	            $members[$Key ] = $member;
			}
        }
		
		if( count($members) == 0 )
		{
            return array('succeed' => 'false', 'error' => 'No rolememberships visible for group', 'params' => var_export($params, TRUE), 'sql' => $sql);
        }
        
        return $members;
    }
    
    function setAgentGroupInfo($params)
    {
		if( is_array($error = secureRequest($params, TRUE)) )
		{
			return $error;
		}
		
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon;
		
        if (isset($params['AgentID'])) {
            $agentID = $params['AgentID'];
        } else {
            $agentID = "";
        }
        if (isset($params['GroupID'])) {
            $groupID = $params['GroupID'];
        } else {
            $groupID = "";
        }
        if (isset($params['SelectedRoleID'])) {
            $roleID  = $params['SelectedRoleID'];
        } else {
            $roleID = "";
        }
        if (isset($params['AcceptNotices'])) {
            $acceptNotices  = $params['AcceptNotices'];
        } else {
            $acceptNotices = 1;
        }
        if (isset($params['ListInProfile'])) {
            $listInProfile  = $params['ListInProfile'];
        } else {
            $listInProfile = 0;
        }

		if( isset($requestingAgent) && ($requestingAgent != $uuidZero) && ($requestingAgent != $agentID) )
		{
            return array('error' => "Agent can only change their own group info", 'params' => var_export($params, TRUE));
		}
		
    
        $sql = " UPDATE "
              ."     osgroupmembership"
              ." SET "
              ."    AgentID = '$agentID'";

        if( isset($params['SelectedRoleID']) )
        {
            $sql .="    , SelectedRoleID = '$roleID'";
        }
        if( isset($params['AcceptNotices']) )
        {
            $sql .="    , AcceptNotices = $acceptNotices";
        }
        if( isset($params['ListInProfile']) )
        {
            $sql .="    , ListInProfile = $listInProfile";
        }
        
        $sql .=" WHERE osgroupmembership.GroupID = '$groupID' AND osgroupmembership.AgentID = '$agentID'";
              
        $memberResults = mysql_query($sql, $groupDBCon);
        if (!$memberResults) 
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }

        
        return array('success'=> 'true');
    }
    
    function getGroupNotices($params)
    {
		if( is_array($error = secureRequest($params, FALSE)) )
		{
			return $error;
		}
		
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon;
        $groupID = $params['GroupID'];

        
        $sql = " SELECT "
              ." GroupID, NoticeID, Timestamp, FromName, Subject, Message, BinaryBucket"
              ." FROM osgroupnotice"
              ." WHERE osgroupnotice.GroupID = '$groupID'";
              
        $results = mysql_query($sql, $groupDBCon);
        if (!$results) 
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        if( mysql_num_rows($results) == 0 )
        {
            return array('succeed' => 'false', 'error' => 'No Notices', 'params' => var_export($params, TRUE), 'sql' => $sql);
        }
        
        $notices = array();
        while($notice = mysql_fetch_assoc($results))
        {
            $NoticeID = $notice['NoticeID'];
            $notices[$NoticeID] = $notice;
        }
        
        return $notices;
    }

    function getGroupNotice($params)
    {
		if( is_array($error = secureRequest($params, FALSE)) )
		{
			return $error;
		}
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon;
        $noticeID = $params['NoticeID'];

        
        $sql = " SELECT "
              ." GroupID, NoticeID, Timestamp, FromName, Subject, Message, BinaryBucket"
              ." FROM osgroupnotice"
              ." WHERE osgroupnotice.NoticeID = '$noticeID'";
              
        $results = mysql_query($sql, $groupDBCon);
        if (!$results) 
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        if( mysql_num_rows($results) == 0 )
        {
            return array('succeed' => 'false', 'error' => 'Group Notice Not Found', 'params' => var_export($params, TRUE), 'sql' => $sql);
        }
        
        return mysql_fetch_assoc($results);
    }
    
    function addGroupNotice($params)
    {
		if( is_array($error = secureRequest($params, TRUE)) )
		{
			return $error;
		}
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon, $groupPowers;
        $groupID  = $params['GroupID'];
        $noticeID = $params['NoticeID'];
        $fromName = addslashes($params['FromName']);
        $subject  = addslashes($params['Subject']);
        $binaryBucket = $params['BinaryBucket'];
        $message      = addslashes($params['Message']);
        $timeStamp    = $params['TimeStamp'];

		if( is_array($error = checkGroupPermission($groupID, $groupPowers['SendNotices'])) )
		{
			return $error;
		}
        
        $sql = " INSERT INTO osgroupnotice"
              ." (GroupID, NoticeID, Timestamp, FromName, Subject, Message, BinaryBucket)"
              ." VALUES "
              ." ('$groupID', '$noticeID', $timeStamp, '$fromName', '$subject', '$message', '$binaryBucket')";
              
        $results = mysql_query($sql, $groupDBCon);
        if (!$results) 
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        return array('success' => 'true');
    }

    
    function addAgentToGroupInvite($params)
    {
		if( is_array($error = secureRequest($params, TRUE)) )
		{
			return $error;
		}
		
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon, $groupPowers;
        $inviteID = $params['InviteID'];
        $groupID = $params['GroupID'];
        $roleID  = $params['RoleID'];
        $agentID = $params['AgentID'];

		if( is_array($error = checkGroupPermission($groupID, $groupPowers['Invite'])) )
		{
			return $error;
		}
		
        // Remove any existing invites for this agent to this group
        $sql = " DELETE FROM osgroupinvite"
              ." WHERE osgroupinvite.AgentID = '$agentID' AND osgroupinvite.GroupID = '$groupID'";
              
        $results = mysql_query($sql, $groupDBCon);
        if (!$results) 
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        // Add new invite for this agent to this group for the specifide role
        $sql = " INSERT INTO osgroupinvite"
              ." (InviteID, GroupID, RoleID, AgentID) VALUES ('$inviteID', '$groupID', '$roleID', '$agentID')";
              
        $results = mysql_query($sql, $groupDBCon);
        if (!$results) 
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        return array('success' => 'true');
    }

    function getAgentToGroupInvite($params)
    {
		if( is_array($error = secureRequest($params, FALSE)) )
		{
			return $error;
		}
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon;
        $inviteID = $params['InviteID'];

        
        $sql = " SELECT GroupID, RoleID, AgentID FROM osgroupinvite"
              ." WHERE osgroupinvite.InviteID = '$inviteID'";
              
        $results = mysql_query($sql, $groupDBCon);
        if (!$results) 
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        if( mysql_num_rows($results) == 1 )
        {
            $inviteInfo = mysql_fetch_assoc($results);
            $groupID  = $inviteInfo['GroupID'];
            $roleID   = $inviteInfo['RoleID'];
            $agentID  = $inviteInfo['AgentID'];
        
            return array('success' => 'true', 'GroupID'=>$groupID, 'RoleID'=>$roleID, 'AgentID'=>$agentID);
        } else {
            return array('succeed' => 'false', 'error' => 'Invitation not found', 'params' => var_export($params, TRUE), 'sql' => $sql);
        }
    }
    
    function removeAgentToGroupInvite($params)
    {
		if( is_array($error = secureRequest($params, TRUE)) )
		{
			return $error;
		}
	
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon;
        $inviteID = $params['InviteID'];
        
        $sql = " DELETE FROM osgroupinvite"
              ." WHERE osgroupinvite.InviteID = '$inviteID'";
              
        $results = mysql_query($sql, $groupDBCon);
        if (!$results) 
        {
            return array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error(), 'params' => var_export($params, TRUE));
        }
        
        return array('success' => 'true');
    }
    
	function secureRequest($params, $write = FALSE)
	{
		global 	$groupWriteKey, $groupReadKey, $verifiedReadKey, $verifiedWriteKey, $groupRequireAgentAuthForWrite, $requestingAgent;
		global  $overrideAgentUserService;

		// Cache this for access by other security functions
		$requestingAgent = $params['RequestingAgentID'];
		
		if( isset($groupReadKey) && ($groupReadKey != '') && (!isset($verifiedReadKey) || ($verifiedReadKey !== TRUE)) )
		{
			if( !isset($params['ReadKey']) || ($params['ReadKey'] != $groupReadKey ) )
			{
				return array('error' => "Invalid (or No) Read Key Specified", 'params' => var_export($params, TRUE));
			} else {
				$verifiedReadKey = TRUE;
			}
		}
		
		if( ($write == TRUE) && isset($groupWriteKey) && ($groupWriteKey != '') && (!isset($verifiedWriteKey) || ($verifiedWriteKey !== TRUE)) )
		{
			if( !isset($params['WriteKey']) || ($params['WriteKey'] != $groupWriteKey ) )
			{
				return array('error' => "Invalid (or No) Write Key Specified", 'params' => var_export($params, TRUE));
			} else {
				$verifiedWriteKey = TRUE;
			}
		}
		
		if( ($write == TRUE) && isset($groupRequireAgentAuthForWrite) && ($groupRequireAgentAuthForWrite == TRUE) )
		{
			// Note: my brain can't do boolean logic this morning, so just putting this here instead of integrating with line above.
			// If the write key has already been verified for this request, don't check it again.  This comes into play with methods that call other methods, such as CreateGroup() which calls Addrole()
			if( isset($verifiedWriteKey) && ($verifiedWriteKey !== TRUE))
			{
				return TRUE;
			}
		
			if( !isset($params['RequestingAgentID']) 
			 || !isset($params['RequestingAgentUserService'])
			 || !isset($params['RequestingSessionID']) 
			 )
			{
				return array('error' => "Requesting AgentID and SessionID must be specified", 'params' => var_export($params, TRUE));
			}
			
			// NOTE: an AgentID and SessionID of $uuidZero will likely be a region making a request, that is not tied to a specific agent making the request.
			
			$UserService = $params['RequestingAgentUserService'];
			if( isset($overrideAgentUserService) && ($overrideAgentUserService != "") )
			{
				$UserService = $overrideAgentUserService;
			}
			
			
			$client = new xmlrpc_client($UserService);
			$client->return_type = 'phpvals';
			
			$verifyParams = new xmlrpcval(array('avatar_uuid' => new xmlrpcval($params['RequestingAgentID'], 'string')
											   ,'session_id'  => new xmlrpcval($params['RequestingSessionID'], 'string'))
										, 'struct');

			$message = new xmlrpcmsg("check_auth_session", array($verifyParams));
			$resp = $client->send($message, 5);
			if ($resp->faultCode()) 
			{
				return array('error' => "Error validating AgentID and SessionID"
				           , 'xmlrpcerror'=> $resp->faultString()
						   , 'params' => var_export($params, TRUE));
			} 
			
			$verifyReturn = $resp->value();
			
			
			if( !isset($verifyReturn['auth_session']) || ($verifyReturn['auth_session'] != 'TRUE') )
			{
				return array('error' => "UserService.check_auth_session() did not return TRUE"
						   , 'userservice' => var_export($verifyReturn, TRUE)
						   , 'params' => var_export($params, TRUE));
				
			}
		}
		
		return TRUE;
	}

	function checkGroupPermission($GroupID, $Permission)
	{
        global $groupEnforceGroupPerms, $requestingAgent, $uuidZero, $groupDBCon, $groupPowers;
		
		if( !isset($Permission) || ($Permission == 0) )
		{
            return array('error' => 'No Permission value specified for checkGroupPermission'
                       , 'Permission' => $Permission);
		}
		
		// If it isn't set to true, then always return true, otherwise verify they have perms
		if( !isset($groupEnforceGroupPerms) || ($groupEnforceGroupPerms != TRUE) )
		{
			return true;
		}
		
		if( !isset($requestingAgent) || ($requestingAgent == $uuidZero) )
		{
            return array('error' => 'Requesting agent was either not specified or not validated.'
                       , 'requestingAgent' => $requestingAgent);
		}
		
		$params = array('AgentID' => $requestingAgent, 'GroupID' => $GroupID);
		$reqAgentMembership = getAgentGroupMembership($params);

		if( isset($reqAgentMembership['error'] ) )
		{
            return array('error' => 'Could not get agent membership for group'
                       , 'params' => var_export($params, TRUE)
					   , 'nestederror' => $reqAgentMembership['error']);
		}

		// Worlds ugliest bitwise operation, EVER
		$PermMask   = $reqAgentMembership['GroupPowers'];
		$PermValue  = $Permission;
		
        global $groupDBCon;
        $sql = " SELECT $PermMask & $PermValue AS Allowed";
        $results = mysql_query($sql, $groupDBCon);
        if (!$results) 
        {
            echo print_r( array('error' => "Could not successfully run query ($sql) from DB: " . mysql_error()));
        }
		$PermMasked = mysql_result($results, 0);
		
		if( $PermMasked != $Permission )
		{
			$permNames = array_flip($groupPowers);
            return array('error' => 'Agent does not have group power to ' . $Permission .'('.$permNames[$Permission].')'
					   , 'PermMasked' => $PermMasked
                       , 'params' => var_export($params, TRUE)
					   , 'permBitMaskSql' => $sql
					   , 'Permission' => $Permission);
		}
		
		/*
		return array('error' => 'Reached end'
				   , 'reqAgentMembership' => var_export($reqAgentMembership, TRUE)
				   , 'GroupID' => $GroupID
				   , 'Permission' => $Permission
				   , 'PermMasked' => $PermMasked
				   );
		*/
		return TRUE;
		
	}
	
    
    $s = new xmlrpc_server(array(
                            "test"                               => array("function" => "test")
                          , "groups.createGroup"                => array("function" => "createGroup", "signature" => $common_sig)
                          , "groups.updateGroup"                => array("function" => "updateGroup", "signature" => $common_sig)
                          , "groups.getGroup"                   => array("function" => "getGroup", "signature" => $common_sig)
                          , "groups.findGroups"                    => array("function" => "findGroups", "signature" => $common_sig)

                          , "groups.getGroupRoles"                => array("function" => "getGroupRoles", "signature" => $common_sig)
                          , "groups.addRoleToGroup"                => array("function" => "addRoleToGroup", "signature" => $common_sig)
                          , "groups.removeRoleFromGroup"        => array("function" => "removeRoleFromGroup", "signature" => $common_sig)
                          , "groups.updateGroupRole"            => array("function" => "updateGroupRole", "signature" => $common_sig)
                          , "groups.getGroupRoleMembers"        => array("function" => "getGroupRoleMembers", "signature" => $common_sig)
                          
                          , "groups.setAgentGroupSelectedRole"    => array("function" => "setAgentGroupSelectedRole", "signature" => $common_sig)
                          , "groups.addAgentToGroupRole"        => array("function" => "addAgentToGroupRole", "signature" => $common_sig)
                          , "groups.removeAgentFromGroupRole"   => array("function" => "removeAgentFromGroupRole", "signature" => $common_sig)
                          
                          , "groups.getGroupMembers"            => array("function" => "getGroupMembers", "signature" => $common_sig)
                          , "groups.addAgentToGroup"            => array("function" => "addAgentToGroup", "signature" => $common_sig)
                          , "groups.removeAgentFromGroup"        => array("function" => "removeAgentFromGroup", "signature" => $common_sig)
                          , "groups.setAgentGroupInfo"            => array("function" => "setAgentGroupInfo", "signature" => $common_sig)

                          , "groups.addAgentToGroupInvite"        => array("function" => "addAgentToGroupInvite", "signature" => $common_sig)
                          , "groups.getAgentToGroupInvite"        => array("function" => "getAgentToGroupInvite", "signature" => $common_sig)
                          , "groups.removeAgentToGroupInvite"    => array("function" => "removeAgentToGroupInvite", "signature" => $common_sig)
                          
                          , "groups.setAgentActiveGroup"        => array("function" => "setAgentActiveGroup", "signature" => $common_sig)
                          , "groups.getAgentGroupMembership"    => array("function" => "getAgentGroupMembership", "signature" => $common_sig)
                          , "groups.getAgentGroupMemberships"    => array("function" => "getAgentGroupMemberships", "signature" => $common_sig)
                          , "groups.getAgentActiveMembership"    => array("function" => "getAgentActiveMembership", "signature" => $common_sig)
                          , "groups.getAgentRoles"                => array("function" => "getAgentRoles", "signature" => $common_sig)
                          
                          , "groups.getGroupNotices"            => array("function" => "getGroupNotices", "signature" => $common_sig)
                          , "groups.getGroupNotice"                => array("function" => "getGroupNotice", "signature" => $common_sig)
                          , "groups.addGroupNotice"                => array("function" => "addGroupNotice", "signature" => $common_sig)
                          
                          
                          
                          
                            ), false);

    $s->functions_parameters_type = 'phpvals';
    if (isset($debugXMLRPC) && $debugXMLRPC > 0 && isset($debugXMLRPCFile) && $debugXMLRPCFile != "") 
	{
		$s->setDebug($debugXMLRPC);
    }
    $s->service();
    if (isset($debugXMLRPC) && $debugXMLRPC > 0 && isset($debugXMLRPCFile) && $debugXMLRPCFile != "") 
	{
		$f = fopen($debugXMLRPCFile,"a");
		fwrite($f,"\n----- " . date("Y-m-d H:i:s") . " -----\n");
		$debugInfo = $s->serializeDebug();
		$debugInfo = split("\n",$debugInfo);
		unset($debugInfo[0]);
		unset($debugInfo[count($debugInfo) -1]);
		$debugInfo = join("\n",$debugInfo);	
		fwrite($f,base64_decode($debugInfo));
		fclose($f);
    }
    
    mysql_close($groupDBCon);

    
?>