#!/bin/bash
source .env
ssh -R "$PROXY_HOST":"$PROXY_HOST" "$DEPLOY_HOST"
