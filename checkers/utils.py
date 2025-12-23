import os
import socket
import logging
import geoip2.database
import config

logger = logging.getLogger(__name__)

USE_PROXY_DEFAULT = os.getenv("USE_PROXY_DEFAULT", str(getattr(config, 'USE_PROXY_DEFAULT', False))).lower() == 'true'

def ip_info(ip_address):
    """
    Get geolocation and ASN information for an IP address.
    Returns country, region, city, zipcode, ASN, and organization.
    """
    geodata = {
        "ip": ip_address,
        "country": "Unknown",
        "country_code": "Unknown",
        "city": "Unknown",
        "region": "Unknown",
        "zipcode": "Unknown",
        "asn": "Unknown",
        "organization": "Unknown"
    }
    
    # Path to MaxMind DBs
    if os.path.exists("/data/GeoLite2-City.mmdb"):
        city_db_path = "/data/GeoLite2-City.mmdb"
        asn_db_path = "/data/GeoLite2-ASN.mmdb"
    elif os.path.exists("docker_data/GeoLite2-City.mmdb"):
        city_db_path = "docker_data/GeoLite2-City.mmdb"
        asn_db_path = "docker_data/GeoLite2-ASN.mmdb"
    else:
        city_db_path = "data/GeoLite2-City.mmdb"
        asn_db_path = "data/GeoLite2-ASN.mmdb"
    
    try:
        if os.path.exists(city_db_path):
            with geoip2.database.Reader(city_db_path) as reader:
                response = reader.city(ip_address)
                geodata["country"] = response.country.name or "Unknown"
                geodata["country_code"] = response.country.iso_code or "Unknown"
                geodata["city"] = response.city.name or "Unknown"
                geodata["region"] = response.subdivisions.most_specific.name or "Unknown"
                geodata["zipcode"] = response.postal.code or "Unknown"
        else:
            logger.warning(f"City database not found at {city_db_path}")

        if os.path.exists(asn_db_path):
            with geoip2.database.Reader(asn_db_path) as reader:
                response = reader.asn(ip_address)
                geodata["asn"] = f"AS{response.autonomous_system_number}"
                geodata["organization"] = response.autonomous_system_organization or "Unknown"
        else:
            logger.warning(f"ASN database not found at {asn_db_path}")
            
    except Exception as e:
        logger.error(f"Error getting GeoIP info for {ip_address}: {e}")
        
    return geodata

def get_ptr_match(host):
    try:
        ip_addr = socket.gethostbyname(host)
        ptr = socket.gethostbyaddr(ip_addr)[0]
        match = "yes" if (ptr == host or ptr.endswith("." + host) or host.endswith("." + ptr)) else "no"
        return ptr, match
    except:
        return None, "no"
