<?php

/*

This file acts as a client to the xmlrpc.php service, to test a call to the getAgentGroupMemberships() method.  This can be used to debug whether or not your installation is failing server side due to php, apache, or other similiar errors.

*/
    include("phpxmlrpclib/xmlrpc.inc");
    include("phpxmlrpclib/xmlrpcs.inc");

			$client = new xmlrpc_client('http://localhost/groups/xmlrpc.php');
			$client->return_type = 'phpvals';
			$client->SetDebug(3);
			
			$verifyParams = new xmlrpcval(array('RequestingAgentID' => new xmlrpcval('b28c0b56-1884-4213-9164-a6169c22f34e', 'string')
											   ,'RequestingSessionID'  => new xmlrpcval('d4af065e-12d4-6147-766f-9fdb3db74600', 'string')
											   ,'RequestingAgentUserService'  => new xmlrpcval('http://osgrid.org:8002', 'string')
											   ,'ReadKey'  => new xmlrpcval('', 'string')
											   ,'WriteKey'  => new xmlrpcval('', 'string')
											   ,'AgentID'  => new xmlrpcval('%%%', 'string')
											   ,'AgentID'  => new xmlrpcval('%%%', 'string')
											   ,'AgentID'  => new xmlrpcval('%%%', 'string')
											   )
										, 'struct');

			$message = new xmlrpcmsg("groups.addAgentToGroupRole", array($verifyParams));
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
						   , 'params' => var_export($params, TRUE));
				
			}

?>