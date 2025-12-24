# Running 

```bash
docker run \
  --name prosellers-unified \
  -p 8888:8888 \
  -p 8801:8801 \
  -p 8803:8803 \
  -p 8804:8804 \
  -p 9900-9950:9900-9950 \
  -v $(pwd)/docker_data:/data \
  --cap-add NET_ADMIN \
  -e LOG_ADMIN_PASSWORD="MyNewStrongPassword" \
  -e API_KEY="New-API-Key" \
  -e LOG_ENCRYPTION_KEY="AnotherStrongKey" \
  -e SEOINFO_ADMIN_PASSWORD="AnotherPassword" \
  prosellers-all-in-one
```
