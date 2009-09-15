/*
 * Copyright (c) Contributors, http://opensimulator.org/
 * See CONTRIBUTORS.TXT for a full list of copyright holders.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the OpenSim Project nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE DEVELOPERS ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

using System.Collections.Generic;
using System.Reflection;
using log4net;
using Nini.Config;

using OpenMetaverse;
using OpenMetaverse.StructuredData;

using OpenSim.Framework;
using OpenSim.Region.CoreModules.Framework.EventQueue;
using OpenSim.Region.Framework.Interfaces;
using OpenSim.Region.Framework.Scenes;

using Caps = OpenSim.Framework.Communications.Capabilities.Caps;
using DirFindFlags = OpenMetaverse.DirectoryManager.DirFindFlags;

namespace OpenSim.Region.CoreModules.Avatar.Groups
{
    public class LocalGroupsModule : IRegionModule, IGroupsModule
    {
        /// <summary>
        /// To use this module, you must specify the following in your OpenSim.ini
        /// [Groups]
        /// Enabled = true;
        /// Module  = LocalGroups;
        /// 
        /// This module is a fast and loose implementation of groups, that uses
        /// local generic collections to store all group information in memory.
        /// 
        /// This does not implement Group Messaging
        /// 
        /// </summary>

        // SendAvatarGroupsReply(UUID avatarID, GroupMembershipData[] data)

        private static readonly ILog m_log =
            LogManager.GetLogger(MethodBase.GetCurrentMethod().DeclaringType);

        private List<Scene> m_SceneList = new List<Scene>();

        private Dictionary<UUID, osGroup> m_Groups = new Dictionary<UUID, osGroup>();

        // There is one GroupMemberInfo entry for each group the Agent is a member of
        // AgentID -> (GroupID -> MembershipInfo)
        private Dictionary<UUID, Dictionary<UUID, osGroupMemberInfo>> m_GroupMemberInfo = new Dictionary<UUID, Dictionary<UUID, osGroupMemberInfo>>();

        // Each agent has one group that they currently have set to Active, this determines
        // somethings like permissions in-world (using group objects for example) and what
        // tag is displayed
        private Dictionary<UUID, UUID> m_AgentActiveGroup = new Dictionary<UUID, UUID>();

        private Dictionary<UUID, IClientAPI> m_ActiveClients = new Dictionary<UUID, IClientAPI>();

        #region IRegionModule Members

        public void Initialise(Scene scene, IConfigSource config)
        {
            IConfig groupsConfig = config.Configs["Groups"];

            if (groupsConfig == null)
            {
                // Do not run this module by default.
                return;
            }
            else
            {
                if (!groupsConfig.GetBoolean("Enabled", false))
                {
                    m_log.Info("[GROUPS]: Groups disabled in configuration");
                    return;
                }

                if (groupsConfig.GetString("Module", "Default") != "LocalGroups")
                    return;
            }

            lock (m_SceneList)
            {
                if (!m_SceneList.Contains(scene))
                {
                    m_SceneList.Add(scene);
                }
            }

            scene.EventManager.OnNewClient += OnNewClient;
            scene.EventManager.OnClientClosed += OnClientClosed;
            scene.EventManager.OnIncomingInstantMessage += OnGridInstantMessage;

            

            scene.RegisterModuleInterface<IGroupsModule>(this);

        }

        public void PostInitialise()
        {
        }

        public void Close()
        {
            m_log.Debug("[GROUPS]: Shutting down group module.");
        }

        public string Name
        {
            get { return "GroupsModule"; }
        }

        public bool IsSharedModule
        {
            get { return true; }
        }

        #endregion

        private void UpdateClientWithGroupInfo(IClientAPI client)
        {
            OnAgentDataUpdateRequest(client, client.AgentId, UUID.Zero);


            // Need to send a group membership update to the client
            // UDP version doesn't seem to behave nicely
            // client.SendGroupMembership(GetMembershipData(client.AgentId));

            GroupMembershipData[] membershipData = GetMembershipData(client.AgentId);

            foreach (GroupMembershipData membership in membershipData)
            {
                osGroupMemberInfo memberInfo = m_GroupMemberInfo[client.AgentId][membership.GroupID];
                osgRole roleInfo = memberInfo.Roles[membership.ActiveRole];

                m_log.InfoFormat("[Groups] {0} member of {1} title active (2)", client.FirstName, membership.GroupName, roleInfo.Name, membership.Active);
            }

            SendGroupMembershipInfoViaCaps(client, membershipData);
            client.SendAvatarGroupsReply(client.AgentId, membershipData);

        }

        #region EventHandlers
        private void OnNewClient(IClientAPI client)
        {
            m_log.InfoFormat("[Groups] {0} called", System.Reflection.MethodBase.GetCurrentMethod().Name);

            // Subscribe to instant messages
            // client.OnInstantMessage += OnInstantMessage;
            


            lock (m_ActiveClients)
            {
                if (!m_ActiveClients.ContainsKey(client.AgentId))
                {
                    client.OnUUIDGroupNameRequest += HandleUUIDGroupNameRequest;
                    client.OnAgentDataUpdateRequest += OnAgentDataUpdateRequest;
                    client.OnDirFindQuery += OnDirFindQuery;
                    m_ActiveClients.Add(client.AgentId, client);
                }
            }

            UpdateClientWithGroupInfo(client);

        }

        void OnDirFindQuery(IClientAPI remoteClient, UUID queryID, string queryText, uint queryFlags, int queryStart)
        {
            if (((DirFindFlags)queryFlags & DirFindFlags.Groups) == DirFindFlags.Groups)
            {
                m_log.InfoFormat("[Groups] {0} called with queryText({1}) queryFlags({2}) queryStart({3})", System.Reflection.MethodBase.GetCurrentMethod().Name, queryText, (DirFindFlags)queryFlags, queryStart);

                List<DirGroupsReplyData> ReplyData = new List<DirGroupsReplyData>();
                int order = 0;
                foreach (osGroup group in m_Groups.Values)
                {
                    if (group.Name.ToLower().Contains(queryText.ToLower()))
                    {
                        if (queryStart <= order)
                        {
                            DirGroupsReplyData data = new DirGroupsReplyData();
                            data.groupID = group.GroupID;
                            data.groupName = group.Name;
                            data.members = group.GroupMembershipCount;
                            data.searchOrder = order;
                            ReplyData.Add(data);
                        }

                        order += 1;
                    }
                }

                remoteClient.SendDirGroupsReply(queryID, ReplyData.ToArray());
            }
            
        }

        private void OnAgentDataUpdateRequest(IClientAPI remoteClient,
                UUID AgentID, UUID SessionID)
        {
            m_log.InfoFormat("[Groups] {0} called with SessionID :: {1}", System.Reflection.MethodBase.GetCurrentMethod().Name, SessionID);

            UUID ActiveGroupID;
            string ActiveGroupTitle = string.Empty;
            string ActiveGroupName = string.Empty;
            ulong ActiveGroupPowers  = (ulong)GroupPowers.None;
            if( m_AgentActiveGroup.TryGetValue(AgentID, out ActiveGroupID) )
            {
                if ((ActiveGroupID != null) && (ActiveGroupID != UUID.Zero))
                {
                    osGroup group = m_Groups[ActiveGroupID];
                    osGroupMemberInfo membership = m_GroupMemberInfo[AgentID][ActiveGroupID];

                    ActiveGroupName = group.Name;
                    if (membership.SelectedTitleRole != UUID.Zero)
                    {
                        ActiveGroupTitle = membership.Roles[membership.SelectedTitleRole].Title;
                    }

                    // Gather up all the powers from agent's roles, then mask them with group mask
                    foreach (osgRole role in membership.Roles.Values)
                    {
                        ActiveGroupPowers |= (ulong)role.Powers;
                    }
                    ActiveGroupPowers &= (ulong)group.PowersMask;
                }
                else
                {
                    ActiveGroupID = UUID.Zero;
                }
            } else {
                // I think this is needed bcasue the TryGetValue() will set this to null
                ActiveGroupID = UUID.Zero;
            }

            string firstname, lastname;
            IClientAPI agent;
            if( m_ActiveClients.TryGetValue(AgentID, out agent) )
            {
                firstname = agent.FirstName;
                lastname = agent.LastName;
            } else {
                firstname = "Unknown";
                lastname = "Unknown";
            }

            m_log.InfoFormat("[Groups] Active Powers {0}, Group {1}, Title {2}", ActiveGroupPowers, ActiveGroupName, ActiveGroupTitle);

            

            remoteClient.SendAgentDataUpdate(AgentID, ActiveGroupID, firstname,
                    lastname, ActiveGroupPowers, ActiveGroupName,
                    ActiveGroupTitle);
        }

        private void OnInstantMessage(IClientAPI client, GridInstantMessage im)
        {
            m_log.WarnFormat("[Groups] {0} is not implemented", System.Reflection.MethodBase.GetCurrentMethod().Name);

            // Probably for monitoring and dealing with group instant messages
        }

        private void OnGridInstantMessage(GridInstantMessage msg)
        {
            m_log.InfoFormat("[Groups] {0} called", System.Reflection.MethodBase.GetCurrentMethod().Name);

            // Trigger the above event handler
            OnInstantMessage(null, msg);
        }

        private void HandleUUIDGroupNameRequest(UUID GroupID,IClientAPI remote_client)
        {
            m_log.InfoFormat("[Groups] {0} called", System.Reflection.MethodBase.GetCurrentMethod().Name);

            string GroupName;

            osGroup group;
            if (m_Groups.TryGetValue(GroupID, out group))
            {
                GroupName = group.Name;
            }
            else
            {
                GroupName = "Unknown";
            }


            remote_client.SendGroupNameReply(GroupID, GroupName);
        }

        private void OnClientClosed(UUID AgentId)
        {
            m_log.InfoFormat("[Groups] {0} called", System.Reflection.MethodBase.GetCurrentMethod().Name);

            lock (m_ActiveClients)
            {
                if (m_ActiveClients.ContainsKey(AgentId))
                {
                    IClientAPI client = m_ActiveClients[AgentId];
                    client.OnUUIDGroupNameRequest -= HandleUUIDGroupNameRequest;
                    client.OnAgentDataUpdateRequest -= OnAgentDataUpdateRequest;
                    client.OnDirFindQuery -= OnDirFindQuery;

                    m_ActiveClients.Remove(AgentId);
                }
            }

        }
        #endregion

        public const GroupPowers AllGroupPowers = GroupPowers.Accountable
                                | GroupPowers.AllowEditLand
                                | GroupPowers.AllowFly
                                | GroupPowers.AllowLandmark
                                | GroupPowers.AllowRez
                                | GroupPowers.AllowSetHome
                                | GroupPowers.AllowVoiceChat
                                | GroupPowers.AssignMember
                                | GroupPowers.AssignMemberLimited
                                | GroupPowers.ChangeActions
                                | GroupPowers.ChangeIdentity
                                | GroupPowers.ChangeMedia
                                | GroupPowers.ChangeOptions
                                | GroupPowers.CreateRole
                                | GroupPowers.DeedObject
                                | GroupPowers.DeleteRole
                                | GroupPowers.Eject
                                | GroupPowers.FindPlaces
                                | GroupPowers.Invite
                                | GroupPowers.JoinChat
                                | GroupPowers.LandChangeIdentity
                                | GroupPowers.LandDeed
                                | GroupPowers.LandDivideJoin
                                | GroupPowers.LandEdit
                                | GroupPowers.LandEjectAndFreeze
                                | GroupPowers.LandGardening
                                | GroupPowers.LandManageAllowed
                                | GroupPowers.LandManageBanned
                                | GroupPowers.LandManagePasses
                                | GroupPowers.LandOptions
                                | GroupPowers.LandRelease
                                | GroupPowers.LandSetSale
                                | GroupPowers.ModerateChat
                                | GroupPowers.ObjectManipulate
                                | GroupPowers.ObjectSetForSale
                                | GroupPowers.ReceiveNotices
                                | GroupPowers.RemoveMember
                                | GroupPowers.ReturnGroupOwned
                                | GroupPowers.ReturnGroupSet
                                | GroupPowers.ReturnNonGroup
                                | GroupPowers.RoleProperties
                                | GroupPowers.SendNotices
                                | GroupPowers.SetLandingPoint
                                | GroupPowers.StartProposal
                                | GroupPowers.VoteOnProposal;

        public const GroupPowers m_DefaultEveryonePowers = GroupPowers.AllowSetHome | GroupPowers.Accountable | GroupPowers.JoinChat | GroupPowers.AllowVoiceChat | GroupPowers.ReceiveNotices | GroupPowers.StartProposal | GroupPowers.VoteOnProposal;

        class osGroup
        {
            public UUID GroupID = UUID.Zero;
            public string Name = string.Empty;
            public string Charter = string.Empty;
            public string MemberTitle = "Everyone";
            public GroupPowers PowersMask = AllGroupPowers;
            public UUID InsigniaID = UUID.Zero;
            public UUID FounderID = UUID.Zero;
            public int MembershipFee = 0;
            public bool OpenEnrollment = true;
            public bool ShowInList = true;
            public int Money = 0;
            public bool AllowPublish = true;
            public bool MaturePublish = true;

            public UUID OwnerRoleID = UUID.Zero;

            public Dictionary<UUID, osgRole> Roles = new Dictionary<UUID, osgRole>();
            public Dictionary<UUID, osGroupMemberInfo> Members = new Dictionary<UUID, osGroupMemberInfo>();

            // Should be calculated
            public int GroupMembershipCount
            {
                get
                {
                    return Members.Count;
                }
            }
            public int GroupRolesCount
            {
                get
                {
                    return Roles.Count;
                }
            }
        }

        class osgRole
        {
            public osGroup Group = null;

            public UUID RoleID = UUID.Zero;
            public string Name = string.Empty;
            public string Title = string.Empty;
            public string Description = string.Empty;
            public GroupPowers Powers = AllGroupPowers;

            public Dictionary<UUID, osGroupMemberInfo> RoleMembers = new Dictionary<UUID, osGroupMemberInfo>();

            // Should be calculated
            public int Members
            {
                get
                {
                    return RoleMembers.Count;
                }
            }

            
        }

        class osGroupMemberInfo
        {
            public Dictionary<UUID, osgRole> Roles = new Dictionary<UUID,osgRole>();

            public UUID AgentID = UUID.Zero;

            public UUID SelectedTitleRole = UUID.Zero;

            // FixMe: This is wrong, the contribution should be per group not per role
            public int Contribution = 0;
            public bool ListInProfile = true;
            public bool AcceptNotices = true;

            // Should be looked up
            public string Title
            {
                get
                {
                    if (Roles.ContainsKey(SelectedTitleRole))
                    {
                        return Roles[SelectedTitleRole].Name;
                    }
                    else
                    {
                        return string.Empty;
                    }
                }
            }
        }

        private void UpdateScenePresenceWithTitle(UUID AgentID, string Title)
        {
            ScenePresence presence = null;
            lock (m_SceneList)
            {
                foreach (Scene scene in m_SceneList)
                {
                    presence = scene.GetScenePresence(AgentID);
                    if (presence != null)
                    {
                        presence.Grouptitle = Title;

                        // FixMe: Ter suggests a "Schedule" method that I can't find.
                        presence.SendFullUpdateToAllClients();
                        break;
                    }
                }
            }
        }


        #region IGroupsModule Members

        public event NewGroupNotice OnNewGroupNotice;

        public void ActivateGroup(IClientAPI remoteClient, UUID groupID)
        {
            m_log.InfoFormat("[Groups] {0} called", System.Reflection.MethodBase.GetCurrentMethod().Name);

            string Title = "";

            if (groupID == UUID.Zero)
            {
                // Setting to no group active, "None"
                m_AgentActiveGroup[remoteClient.AgentId] = groupID;
            }
            else if (m_Groups.ContainsKey(groupID))
            {
                osGroup group = m_Groups[groupID];
                if (group.Members.ContainsKey(remoteClient.AgentId))
                {
                    m_AgentActiveGroup[remoteClient.AgentId] = groupID;

                    osGroupMemberInfo member = group.Members[remoteClient.AgentId];

                    Title = group.Roles[member.SelectedTitleRole].Title;
                }
            }

            UpdateScenePresenceWithTitle(remoteClient.AgentId, Title);

            UpdateClientWithGroupInfo(remoteClient);
        }

        public List<GroupTitlesData> GroupTitlesRequest(IClientAPI remoteClient, UUID groupID)
        {
            m_log.InfoFormat("[Groups] {0} called", System.Reflection.MethodBase.GetCurrentMethod().Name);

            List<GroupTitlesData> groupTitles = new List<GroupTitlesData>();

            Dictionary<UUID, osGroupMemberInfo> memberships;

            if (m_GroupMemberInfo.TryGetValue(remoteClient.AgentId, out memberships))
            {
                if( memberships.ContainsKey(groupID) )
                {
                    osGroupMemberInfo member = memberships[groupID];

                    foreach (osgRole role in member.Roles.Values)
                    {
                        GroupTitlesData data;
                        data.Name = role.Title;
                        data.UUID = role.RoleID;

                        if (role.RoleID == member.SelectedTitleRole)
                        {
                            data.Selected = true;
                        }
                        else
                        {
                            data.Selected = false;
                        }

                        groupTitles.Add(data);
                    }
                }
            }

            return groupTitles;
        }

        public List<GroupMembersData> GroupMembersRequest(IClientAPI remoteClient, UUID groupID)
        {
            m_log.InfoFormat("[Groups] {0} called", System.Reflection.MethodBase.GetCurrentMethod().Name);

            List<GroupMembersData> groupMembers = new List<GroupMembersData>();

            osGroup group;
            if (m_Groups.TryGetValue(groupID, out group))
            {
                foreach (osGroupMemberInfo member in group.Members.Values)
                {
                    GroupMembersData data = new GroupMembersData();
                    data.AcceptNotices = member.AcceptNotices;
                    data.AgentID = member.AgentID;
                    data.Contribution = member.Contribution;
                    data.IsOwner = member.Roles.ContainsKey(group.OwnerRoleID);
                    data.ListInProfile = member.ListInProfile;

                    data.AgentPowers = 0;

                    foreach (osgRole role in member.Roles.Values)
                    {
                        data.AgentPowers |= (ulong)role.Powers;
                        if (role.RoleID == member.SelectedTitleRole)
                        {
                            data.Title = role.Title;
                        }
                    }

                    // FIXME: need to look this up.
                    // data.OnlineStatus

                    groupMembers.Add(data);
                }
            }

            return groupMembers;
        }

        public List<GroupRolesData> GroupRoleDataRequest(IClientAPI remoteClient, UUID groupID)
        {
            m_log.InfoFormat("[Groups] {0} called", System.Reflection.MethodBase.GetCurrentMethod().Name);

            List<GroupRolesData> rolesData = new List<GroupRolesData>();

            osGroup group;
            if (m_Groups.TryGetValue(groupID, out group))
            {
                foreach (osgRole role in group.Roles.Values)
                {
                    GroupRolesData data = new GroupRolesData();
                    data.Description = role.Description;
                    data.Members = role.Members;
                    data.Name = role.Name;
                    data.Powers = (ulong)role.Powers;
                    data.RoleID = role.RoleID;
                    data.Title = role.Title;

                    m_log.DebugFormat("[Groups] Role {0}  :: Powers {1}", role.Name, data.Powers);

                    rolesData.Add(data);
                }
            }

            return rolesData;
        }

        public List<GroupRoleMembersData> GroupRoleMembersRequest(IClientAPI remoteClient, UUID groupID)
        {
            m_log.InfoFormat("[Groups] {0} called", System.Reflection.MethodBase.GetCurrentMethod().Name);

            List<GroupRoleMembersData> roleMemberData = new List<GroupRoleMembersData>();

            osGroup group;
            if (m_Groups.TryGetValue(groupID, out group))
            {
                foreach (osgRole role in group.Roles.Values)
                {
                    foreach (osGroupMemberInfo member in role.RoleMembers.Values)
                    {
                        GroupRoleMembersData data = new GroupRoleMembersData();
                        data.RoleID = role.RoleID;
                        data.MemberID = member.AgentID;

                        m_log.DebugFormat("[Groups] Role {0}  :: Member {1}", role.Name, member.AgentID);

                        roleMemberData.Add(data);
                    }
                }
            }

            return roleMemberData;
        }

        public GroupProfileData GroupProfileRequest(IClientAPI remoteClient, UUID groupID)
        {
            m_log.InfoFormat("[Groups] {0} called", System.Reflection.MethodBase.GetCurrentMethod().Name);

            GroupProfileData profile = new GroupProfileData();

            osGroup group;
            if (m_Groups.TryGetValue(groupID, out group))
            {
                profile.AllowPublish = group.AllowPublish;
                profile.Charter = group.Charter;
                profile.FounderID = group.FounderID;
                profile.GroupID = group.GroupID;
                profile.GroupMembershipCount = group.GroupMembershipCount;
                profile.GroupRolesCount = group.GroupRolesCount;
                profile.InsigniaID = group.InsigniaID;
                profile.MaturePublish = group.MaturePublish;
                profile.MembershipFee = group.MembershipFee;
                profile.MemberTitle = group.MemberTitle;
                profile.Money = group.Money;
                profile.Name = group.Name;
                profile.OpenEnrollment = group.OpenEnrollment;
                profile.OwnerRole = group.OwnerRoleID;
                profile.PowersMask = (ulong)group.PowersMask;
                profile.ShowInList = group.ShowInList;
                
            }

            return profile;
        }

        

        public GroupMembershipData[] GetMembershipData(UUID UserID)
        {
            m_log.InfoFormat("[Groups] {0} called", System.Reflection.MethodBase.GetCurrentMethod().Name);

            List<GroupMembershipData> membershipData = new List<GroupMembershipData>();

            foreach ( UUID GroupID in m_Groups.Keys)
            {
                GroupMembershipData membership = GetMembershipData(GroupID, UserID);
                if (membership != null)
                {
                    membershipData.Add(membership);
                }
            }

            return membershipData.ToArray();
        }

        public GroupMembershipData GetMembershipData(UUID GroupID, UUID UserID)
        {
            m_log.InfoFormat("[Groups] {0} called", System.Reflection.MethodBase.GetCurrentMethod().Name);

            osGroup group;
            if (m_Groups.TryGetValue(GroupID, out group))
            {

                osGroupMemberInfo member;
                if (group.Members.TryGetValue(UserID, out member))
                {
                    GroupMembershipData data = new GroupMembershipData();
                    data.AcceptNotices = member.AcceptNotices;
                    data.Contribution = member.Contribution;
                    data.ListInProfile = member.ListInProfile;

                    data.ActiveRole = member.SelectedTitleRole;
                    data.GroupTitle = member.Roles[member.SelectedTitleRole].Title;

                    foreach (osgRole role in member.Roles.Values)
                    {
                        data.GroupPowers |= (ulong)role.Powers;

                        if (m_AgentActiveGroup.ContainsKey(UserID) && (m_AgentActiveGroup[UserID] == role.RoleID))
                        {
                            data.Active = true;
                        }
                    }

                    data.AllowPublish = group.AllowPublish;
                    data.Charter = group.Charter;
                    data.FounderID = group.FounderID;
                    data.GroupID = group.GroupID;
                    data.GroupName = group.Name;
                    data.GroupPicture = group.InsigniaID;
                    data.MaturePublish = group.MaturePublish;
                    data.MembershipFee = group.MembershipFee;
                    data.OpenEnrollment = group.OpenEnrollment;
                    data.ShowInList = group.ShowInList;

                    return data;

                }
            }

            return null;
        }

        public void UpdateGroupInfo(IClientAPI remoteClient, UUID groupID, string charter, bool showInList, UUID insigniaID, int membershipFee, bool openEnrollment, bool allowPublish, bool maturePublish)
        {
            m_log.InfoFormat("[Groups] {0} called", System.Reflection.MethodBase.GetCurrentMethod().Name);

            // TODO: Security Check?

            osGroup group;
            if (m_Groups.TryGetValue(groupID, out group))
            {
                group.Charter = charter;
                group.ShowInList = showInList;
                group.InsigniaID = insigniaID;
                group.MembershipFee = membershipFee;
                group.OpenEnrollment = openEnrollment;
                group.AllowPublish = allowPublish;
                group.MaturePublish = maturePublish;
            }
        }

        public void SetGroupAcceptNotices(IClientAPI remoteClient, UUID groupID, bool acceptNotices, bool listInProfile)
        {
            // TODO: Security Check?

            m_log.WarnFormat("[Groups] {0} is not implemented", System.Reflection.MethodBase.GetCurrentMethod().Name);
        }

        private osGroupMemberInfo AddAgentToGroup(UUID AgentID, osGroup existingGroup)
        {
            if (existingGroup.Members.ContainsKey(AgentID))
            {
                return existingGroup.Members[AgentID];
            }

            osGroupMemberInfo newMembership = new osGroupMemberInfo();
            newMembership.AgentID = AgentID;
            newMembership.AcceptNotices = true;
            newMembership.Contribution = 0;
            newMembership.ListInProfile = true;
            newMembership.SelectedTitleRole = UUID.Zero; // Everyone Role

            newMembership.Roles.Add(UUID.Zero, existingGroup.Roles[UUID.Zero]);
            existingGroup.Roles[UUID.Zero].RoleMembers.Add(AgentID, newMembership);

            // Make sure the member is in the big Group Membership lookup dictionary
            if (!m_GroupMemberInfo.ContainsKey(AgentID))
            {
                m_GroupMemberInfo.Add(AgentID, new Dictionary<UUID, osGroupMemberInfo>());
            }

            // Add this particular membership to the lookup
            m_GroupMemberInfo[AgentID].Add(existingGroup.GroupID, newMembership);

            // Add member to group's local list of members
            existingGroup.Members.Add(AgentID, newMembership);

            return newMembership;
        }

        private osGroupMemberInfo AddAgentToGroup(UUID AgentID, UUID GroupID)
        {
            osGroup group;
            if (m_Groups.TryGetValue(GroupID, out group))
            {
                return AddAgentToGroup(AgentID, group);
            }

            // FixMe: Need to do something here if group doesn't exist
            return null;
        }

        private osgRole AddRole2Group(osGroup group, string name, string description, string title, GroupPowers powers)
        {
            return AddRole2Group(group, name, description, title, powers, UUID.Random());
        }

        private osgRole AddRole2Group(osGroup group, string name, string description, string title, GroupPowers powers, UUID roleid)
        {
            osgRole newRole = new osgRole();
            // everyoneRole.RoleID = UUID.Random();
            newRole.RoleID = roleid;
            newRole.Name = name;
            newRole.Description = description;
            newRole.Powers = (GroupPowers)powers & group.PowersMask;
            newRole.Title = title;
            newRole.Group = group;

            group.Roles.Add(newRole.RoleID, newRole);

            return newRole;
        }

        public UUID CreateGroup(IClientAPI remoteClient, string name, string charter, bool showInList, UUID insigniaID, int membershipFee, bool openEnrollment, bool allowPublish, bool maturePublish)
        {
            m_log.InfoFormat("[Groups] {0} called", System.Reflection.MethodBase.GetCurrentMethod().Name);

            foreach (osGroup existingGroup in m_Groups.Values)
            {
                if (existingGroup.Name.ToLower().Trim().Equals(name.ToLower().Trim()))
                {
                    remoteClient.SendCreateGroupReply(UUID.Zero, false, "A group with the same name already exists.");
                    return UUID.Zero;
                }
            }

            osGroup newGroup = new osGroup();
            newGroup.GroupID = UUID.Random();
            newGroup.Name = name;
            newGroup.Charter = charter;
            newGroup.ShowInList = showInList;
            newGroup.InsigniaID = insigniaID;
            newGroup.MembershipFee = membershipFee;
            newGroup.OpenEnrollment = openEnrollment;
            newGroup.AllowPublish = allowPublish;
            newGroup.MaturePublish = maturePublish;

            newGroup.PowersMask = AllGroupPowers;
            newGroup.FounderID = remoteClient.AgentId;


            // Setup members role
            osgRole everyoneRole = AddRole2Group(newGroup, "Everyone", "Everyone in the group is in the everyone role.", "Everyone Title", m_DefaultEveryonePowers, UUID.Zero);

            // Setup owners role
            osgRole ownerRole = AddRole2Group(newGroup, "Owners", "Owners of " + newGroup.Name, "Owner of " + newGroup.Name, AllGroupPowers);


            osGroupMemberInfo Member = AddAgentToGroup(remoteClient.AgentId, newGroup);

            // Put the founder in the owner and everyone role
            Member.Roles.Add(ownerRole.RoleID, ownerRole);

            // Add founder to owner & everyone's local lists of members
            ownerRole.RoleMembers.Add(Member.AgentID, Member);

            // Add group to module 
            m_Groups.Add(newGroup.GroupID, newGroup);


            remoteClient.SendCreateGroupReply(newGroup.GroupID, true, "Group created successfullly");

            // Set this as the founder's active group
            ActivateGroup(remoteClient, newGroup.GroupID);

            // The above sends this out too as of 4/3/09
            // UpdateClientWithGroupInfo(remoteClient);


            return newGroup.GroupID;
        }

        public GroupNoticeData[] GroupNoticesListRequest(IClientAPI remoteClient, UUID GroupID)
        {
            m_log.WarnFormat("[Groups] {0} is not implemented", System.Reflection.MethodBase.GetCurrentMethod().Name);

            return new GroupNoticeData[0];
        }

        /// <summary>
        /// Get the title of the agent's current role.
        /// </summary>
        public string GetGroupTitle(UUID avatarID)
        {
            m_log.InfoFormat("[Groups] {0} called", System.Reflection.MethodBase.GetCurrentMethod().Name);
            
            UUID activeGroupID;
            // Check if they have an active group listing
            if (m_AgentActiveGroup.TryGetValue(avatarID, out activeGroupID))
            {
                // See if they have any group memberships
                if( m_GroupMemberInfo.ContainsKey(avatarID) )
                {
                    // See if the have a group membership for the group they have marked active
                    if( m_GroupMemberInfo[avatarID].ContainsKey(activeGroupID) )
                    {
                        osGroupMemberInfo membership = m_GroupMemberInfo[avatarID][activeGroupID];

                        // Return the title of the role they currently have marked as their selected active role/title
                        return membership.Roles[membership.SelectedTitleRole].Title;
                    }
                }
            }

            return string.Empty;
        }

        /// <summary>
        /// Change the current Active Group Role for Agent
        /// </summary>
        public void GroupTitleUpdate(IClientAPI remoteClient, UUID GroupID, UUID TitleRoleID)
        {
            m_log.InfoFormat("[Groups] {0} called", System.Reflection.MethodBase.GetCurrentMethod().Name);

            // See if they have any group memberships
            if (m_GroupMemberInfo.ContainsKey(remoteClient.AgentId))
            {
                // See if the have a group membership for the group whose title they want to change
                if (m_GroupMemberInfo[remoteClient.AgentId].ContainsKey(GroupID))
                {
                    osGroupMemberInfo membership = m_GroupMemberInfo[remoteClient.AgentId][GroupID];

                    // make sure they're a member of the role they're trying to mark active
                    if (membership.Roles.ContainsKey(TitleRoleID))
                    {
                        membership.SelectedTitleRole = TitleRoleID;
                        UpdateClientWithGroupInfo(remoteClient);
                    }
                }
            }
        }


        public void GroupRoleUpdate(IClientAPI remoteClient, UUID GroupID, UUID RoleID, string name, string description, string title, ulong powers, byte updateType)
        {
            m_log.InfoFormat("[Groups] {0} called", System.Reflection.MethodBase.GetCurrentMethod().Name);

            // TODO: Security Check?

            osGroup group;
            if (m_Groups.TryGetValue(GroupID, out group))
            {
                osgRole role;

                switch ((OpenMetaverse.GroupRoleUpdate)updateType)
                {
                    case OpenMetaverse.GroupRoleUpdate.Create:
                        role = AddRole2Group(group, name, description, title, (GroupPowers)powers, RoleID == UUID.Zero ? UUID.Random() : RoleID);
                        break;

                    case OpenMetaverse.GroupRoleUpdate.Delete:
                        if (group.Roles.TryGetValue(RoleID, out role))
                        {
                            List<UUID> Members2Remove = new List<UUID>();

                            // Remove link from membership to this role
                            foreach(osGroupMemberInfo membership in role.RoleMembers.Values)
                            {
                                membership.Roles.Remove(role.RoleID);
                                Members2Remove.Add(membership.AgentID);
                                if (membership.SelectedTitleRole == role.RoleID)
                                {
                                    membership.SelectedTitleRole = UUID.Zero;

                                }
                            }

                            // Remove link from this role to the membership
                            foreach (UUID member in Members2Remove)
                            {
                                role.RoleMembers.Remove(member);
                            }

                            // Remove the role from the group
                            group.Roles.Remove(RoleID);


                        }
                        break;

                    case OpenMetaverse.GroupRoleUpdate.UpdateAll:
                    case OpenMetaverse.GroupRoleUpdate.UpdateData:
                    case OpenMetaverse.GroupRoleUpdate.UpdatePowers:
                        if (group.Roles.TryGetValue(RoleID, out role))
                        {
                            role.Name = name;
                            role.Description = description;
                            role.Title = title;
                            role.Powers = (GroupPowers)powers;

                        }
                        break;

                    case OpenMetaverse.GroupRoleUpdate.NoUpdate:
                    default:
                        // No Op
                        break;

                }

                UpdateClientWithGroupInfo(remoteClient);
            }
        }

        public void GroupRoleChanges(IClientAPI remoteClient, UUID GroupID, UUID RoleID, UUID MemberID, uint changes)
        {
            // Todo: Security check

            osGroup group;
            if (m_Groups.TryGetValue(GroupID, out group))
            {
                // Must already be a member
                osGroupMemberInfo membership;
                if (m_GroupMemberInfo.ContainsKey(MemberID) && m_GroupMemberInfo[MemberID].ContainsKey(GroupID))
                {
                    membership = m_GroupMemberInfo[MemberID][GroupID];

                    osgRole role;
                    if (group.Roles.TryGetValue(RoleID, out role))
                    {

                        switch (changes)
                        {
                            case 0:
                                // Add
                                    membership.Roles[RoleID] = role;
                                    role.RoleMembers[MemberID] = membership;
                                break;
                            case 1:
                                // Remove
                                if (membership.Roles.ContainsKey(RoleID))
                                {
                                    membership.Roles.Remove(RoleID);
                                }
                                if (role.RoleMembers.ContainsKey(MemberID))
                                {
                                    role.RoleMembers.Remove(MemberID);
                                }
                                
                                break;
                            default:
                                m_log.ErrorFormat("[Groups] {0} does not understand changes == {1}", System.Reflection.MethodBase.GetCurrentMethod().Name, changes);
                                break;
                        }
                        UpdateClientWithGroupInfo(remoteClient);
                    }
                }
            }
        }

        public void GroupNoticeRequest(IClientAPI remoteClient, UUID groupNoticeID)
        {
            m_log.WarnFormat("[Groups] {0} is not implemented", System.Reflection.MethodBase.GetCurrentMethod().Name);
        }

        public GridInstantMessage CreateGroupNoticeIM(UUID agentID, UUID groupNoticeID, byte dialog)
        {
            m_log.WarnFormat("[Groups] {0} is not properly implemented", System.Reflection.MethodBase.GetCurrentMethod().Name);

            IClientAPI agent = m_ActiveClients[agentID];
            return new GridInstantMessage(agent.Scene, agentID, agent.Name, UUID.Zero, dialog, string.Empty, false, Vector3.Zero);
        }

        public void SendAgentGroupDataUpdate(IClientAPI remoteClient)
        {
            m_log.InfoFormat("[Groups] {0} called", System.Reflection.MethodBase.GetCurrentMethod().Name);

            UpdateClientWithGroupInfo(remoteClient);

        }

        public void JoinGroupRequest(IClientAPI remoteClient, UUID GroupID)
        {
            // Should check to see if OpenEnrollment, or if there's an outstanding invitation

            if (AddAgentToGroup(remoteClient.AgentId, GroupID) != null)
            {
                remoteClient.SendJoinGroupReply(GroupID, true);

                UpdateClientWithGroupInfo(remoteClient);
            }

            remoteClient.SendJoinGroupReply(GroupID, false);
        }

        public void LeaveGroupRequest(IClientAPI remoteClient, UUID GroupID)
        {
            osGroup group;
            if (m_Groups.TryGetValue(GroupID, out group))
            {
                if (group.Members.ContainsKey(remoteClient.AgentId))
                {
                    osGroupMemberInfo member = group.Members[remoteClient.AgentId];

                    // Remove out of each role in group
                    foreach (osgRole role in member.Roles.Values)
                    {
                        role.RoleMembers.Remove(member.AgentID);
                    }

                    // Remove member from Group's list of members
                    group.Members.Remove(member.AgentID);

                    // Make sure this group isn't the user's current active group
                    if (m_AgentActiveGroup.ContainsKey(member.AgentID) && (m_AgentActiveGroup[member.AgentID] == group.GroupID))
                    {
                        ActivateGroup(remoteClient, UUID.Zero);
                    }

                    // Remove from global lookup index
                    if (m_GroupMemberInfo.ContainsKey(member.AgentID))
                    {
                        m_GroupMemberInfo[member.AgentID].Remove(group.GroupID);
                    }

                    UpdateClientWithGroupInfo(remoteClient);

                    remoteClient.SendLeaveGroupReply(GroupID, true);
                }
            }

            remoteClient.SendLeaveGroupReply(GroupID, false);
        }

        public void EjectGroupMemberRequest(IClientAPI remoteClient, UUID GroupID, UUID EjecteeID)
        {
            // Todo: Security check?

            m_log.WarnFormat("[Groups] {0} is not implemented", System.Reflection.MethodBase.GetCurrentMethod().Name);
            // SendEjectGroupMemberReply(UUID agentID, UUID groupID, bool success)
        }

        public void InviteGroupRequest(IClientAPI remoteClient, UUID GroupID, UUID InviteeID, UUID RoleID)
        {
            m_log.WarnFormat("[Groups] {0} is not implemented", System.Reflection.MethodBase.GetCurrentMethod().Name);
        }

        #endregion

        void SendGroupMembershipInfoViaCaps(IClientAPI remoteClient, GroupMembershipData[] data)
        {
            m_log.InfoFormat("[Groups] {0} called", System.Reflection.MethodBase.GetCurrentMethod().Name);

            OSDArray AgentData = new OSDArray(1);
            OSDMap AgentDataMap = new OSDMap(1);
            AgentDataMap.Add("AgentID", OSD.FromUUID(remoteClient.AgentId));
            AgentData.Add(AgentDataMap);


            OSDArray GroupData = new OSDArray(data.Length);
            OSDArray NewGroupData = new OSDArray(data.Length);

            foreach (GroupMembershipData membership in data)
            {
                OSDMap GroupDataMap = new OSDMap(6);
                OSDMap NewGroupDataMap = new OSDMap(1);

                GroupDataMap.Add("GroupID", OSD.FromUUID(membership.GroupID));
                GroupDataMap.Add("GroupPowers", OSD.FromBinary(membership.GroupPowers));
                GroupDataMap.Add("AcceptNotices", OSD.FromBoolean(membership.AcceptNotices));
                GroupDataMap.Add("GroupInsigniaID", OSD.FromUUID(membership.GroupPicture));
                GroupDataMap.Add("Contribution", OSD.FromInteger(membership.Contribution));
                GroupDataMap.Add("GroupName", OSD.FromString(membership.GroupName));
                NewGroupDataMap.Add("ListInProfile", OSD.FromBoolean(membership.ListInProfile));

                GroupData.Add(GroupDataMap);
                NewGroupData.Add(NewGroupDataMap);
            }

            OSDMap llDataStruct = new OSDMap(3);
            llDataStruct.Add("AgentData", AgentData);
            llDataStruct.Add("GroupData", GroupData);
            llDataStruct.Add("NewGroupData", NewGroupData);

            IEventQueue queue = remoteClient.Scene.RequestModuleInterface<IEventQueue>();

            if (queue != null)
            {
                queue.Enqueue(EventQueueHelper.buildEvent("AgentGroupDataUpdate", llDataStruct), remoteClient.AgentId);
            }

        }
    }
}
