using System;
using System.Collections.Generic;
using System.IO;
using System.Text;
using System.Xml;

namespace Xml2INI
{
    class Xml2INI_App
    {
        static void Main(string[] args)
        {
            if (args.Length < 1)
            {
                DisplayUsage();
                return;
            }

            string[] files2convert;
            string ImportDir;

            if (Directory.Exists(args[0]))
            {
                // importing a directory of xml files

                ImportDir = args[0]; 
                files2convert = Directory.GetFiles(ImportDir, "*.xml");
            }
            else if (File.Exists(args[0]))
            {
                ImportDir = Path.GetDirectoryName(args[0]);                
                files2convert = new string[] { args[0] };
            }
            else
            {
                Console.WriteLine("Could not find directory or file : {0}", args[0]);
                DisplayUsage();
                return;
            }


            List<RegionConfig> RegionConfigs = new List<RegionConfig>();


            foreach (string file in files2convert)
            {
                Console.WriteLine("Reading: {0}", file);
                XmlDocument xml = new XmlDocument();
                xml.Load(file);



                foreach (XmlNode config in xml.SelectNodes("//Root/Config"))
                {
                    /*
                     * <Root>
                     *  <Config 
                     *      sim_UUID="f6712d7c-7374-4ca3-b1cb-8ebc67dd1652" 
                     *      sim_name="Leeward Isles 01" 
                     *      sim_location_x="10027" 
                     *      sim_location_y="9975" 
                     *      internal_ip_address="74.208.149.87" 
                     *      internal_ip_port="9100" 
                     *      allow_alternate_ports="false" 
                     *      external_host_name="74.208.149.87" 
                     *      master_avatar_uuid="00000000-0000-0000-0000-000000000000" 
                     *      master_avatar_first="Static" 
                     *      master_avatar_last="Sprocket" 
                     *      master_avatar_pass="" 
                     *      lastmap_uuid="7a204755-fdbb-422c-8261-5ef8e3882ee3" 
                     *      lastmap_refresh="1253902766" 
                     *      nonphysical_prim_max="0" 
                     *      physical_prim_max="0" 
                     *      clamp_prim_size="false" 
                     *      object_capacity="0" 
                     *      scope_id="00000000-0000-0000-0000-000000000000" />
                     * </root>
                     */
                    RegionConfig regconfig = new RegionConfig();

                    regconfig.sim_UUID = config.Attributes["sim_UUID"].Value;
                    regconfig.sim_name = config.Attributes["sim_name"].Value;

                    Console.WriteLine("Processing: {0}", regconfig.sim_name);

                    int.TryParse(config.Attributes["sim_location_x"].Value, out regconfig.sim_location_x);
                    int.TryParse(config.Attributes["sim_location_y"].Value, out regconfig.sim_location_y);

                    regconfig.internal_ip_address = config.Attributes["internal_ip_address"].Value;
                    regconfig.internal_ip_port = config.Attributes["internal_ip_port"].Value;
                    regconfig.allow_alternate_ports = config.Attributes["allow_alternate_ports"].Value;
                    regconfig.external_host_name = config.Attributes["external_host_name"].Value;
                    regconfig.master_avatar_uuid = config.Attributes["master_avatar_uuid"].Value;
                    regconfig.master_avatar_first = config.Attributes["master_avatar_first"].Value;
                    regconfig.master_avatar_last = config.Attributes["master_avatar_last"].Value;
                    regconfig.master_avatar_pass = config.Attributes["master_avatar_pass"].Value;
                    regconfig.lastmap_uuid = config.Attributes["lastmap_uuid"].Value;
                    regconfig.lastmap_refresh = config.Attributes["lastmap_refresh"].Value;
                    regconfig.nonphysical_prim_max = config.Attributes["nonphysical_prim_max"].Value;
                    regconfig.physical_prim_max = config.Attributes["physical_prim_max"].Value;
                    regconfig.clamp_prim_size = config.Attributes["clamp_prim_size"].Value;
                    regconfig.object_capacity = config.Attributes["object_capacity"].Value;

                    RegionConfigs.Add(regconfig);
                }
            }

            if (RegionConfigs.Count == 0)
            {
                Console.WriteLine("No regions processed.");
                return;
            }

            Console.WriteLine();
            Console.WriteLine("Sorting {0} Regions", RegionConfigs.Count);

            RegionConfigs.Sort(new RegionSort());


            string RegionINI = Path.Combine(ImportDir, "region.ini");

            Console.WriteLine();
            Console.WriteLine("Writing to {0}", RegionINI);

            foreach (RegionConfig regconfig in RegionConfigs)
            {
                File.AppendAllText(RegionINI, regconfig.ToINI() + Environment.NewLine);
            }
            Console.WriteLine();
            Console.WriteLine("Done");
        }

        static void DisplayUsage()
        {
            Console.WriteLine();
            Console.WriteLine("Usage: Xml2INI [region_xml_dir]");
            Console.WriteLine("Usage: Xml2INI [region_xml_file]");
        }
    }

    public class RegionSort : IComparer<RegionConfig>
    {

        #region IComparer<RegionConfig> Members

        public int Compare(RegionConfig regionA, RegionConfig regionB)
        {
            if (regionA.sim_location_x == regionB.sim_location_x)
            {
                if (regionA.sim_location_y == regionB.sim_location_y)
                {
                    return 0;
                } else {
                    return regionA.sim_location_y > regionB.sim_location_y ? 1 : -1;
                }
            }
            else
            {
                return regionA.sim_location_x > regionB.sim_location_x ? 1 : -1;
            }
        }

        #endregion
    }

    public class RegionConfig
    {
        public string sim_UUID = "";
        public string sim_name = "";
        public int sim_location_x = 0;
        public int sim_location_y = 0;
        public string internal_ip_address = "";
        public string internal_ip_port = "";
        public string allow_alternate_ports = "";
        public string external_host_name = "";
        public string master_avatar_uuid = "";
        public string master_avatar_first = "";
        public string master_avatar_last = "";
        public string master_avatar_pass = "";
        public string lastmap_uuid = "";
        public string lastmap_refresh = "";
        public string nonphysical_prim_max = "";
        public string physical_prim_max = "";
        public string clamp_prim_size = "";
        public string object_capacity = "";
        public string scope_id = "";

        public string ToINI()
        {
            /*
            [Sea]
            RegionUUID = 2a86dbf1-9d96-11de-8a39-0800200c9a66
            Location = 10222,10218
            InternalAddress = 0.0.0.0
            InternalPort = 9000
            AllowAlternatePorts = False
            ExternalHostName = 67.205.138.133
            MasterAvatarFirstName = Bri
            MasterAvatarLastName = Hasp
            MasterAvatarSandboxPassword = 01234567
             */
            StringBuilder sb = new StringBuilder();
            sb.AppendLine(string.Format("[{0}]", sim_name));
            sb.AppendLine(string.Format("RegionUUID = {0}", sim_UUID));
            sb.AppendLine(string.Format("Location = {0},{1}", sim_location_x, sim_location_y));
            sb.AppendLine(string.Format("InternalAddress = {0}", internal_ip_address));
            sb.AppendLine(string.Format("InternalPort = {0}", internal_ip_port));
            sb.AppendLine(string.Format("AllowAlternatePorts = {0}", allow_alternate_ports));
            sb.AppendLine(string.Format("ExternalHostName = {0}", external_host_name));
            sb.AppendLine(string.Format("MasterAvatarFirstName = {0}", master_avatar_first));
            sb.AppendLine(string.Format("MasterAvatarLastName = {0}", master_avatar_last));
            sb.AppendLine(string.Format("MasterAvatarSandboxPassword = {0}", master_avatar_pass));

            return sb.ToString();
        }
    }
}
