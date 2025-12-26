import * as cdk from 'aws-cdk-lib';
import { Construct } from 'constructs';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as iam from 'aws-cdk-lib/aws-iam';
import * as logs from 'aws-cdk-lib/aws-logs';
import * as rds from 'aws-cdk-lib/aws-rds';
import * as s3 from 'aws-cdk-lib/aws-s3';
import * as route53 from 'aws-cdk-lib/aws-route53';
import * as ses from 'aws-cdk-lib/aws-ses';
import * as secretsmanager from 'aws-cdk-lib/aws-secretsmanager';

export class EvolnowStack extends cdk.Stack {
  constructor(scope: Construct, id: string, props?: cdk.StackProps) {
    super(scope, id, props);

    // -----------------------------
    // Config
    // -----------------------------
    const domainName = 'evolnow.org';
    const letsEncryptEmail = 'admin@evolnow.org';
    const webImage = 'brandonsoccer22/evolnow-app:latest'; // PHP-FPM app image.
    const instanceType = new ec2.InstanceType('t4g.small'); // ARM instance; image must support arm64.

    const hostRoot = '/srv/evolnow';
    const appDir = `${hostRoot}/app`; // Host path for extracted app files.
    const logsDir = `${appDir}/storage/logs`; // Laravel log path for CloudWatch.
    const typesenseDataDir = `${hostRoot}/typesense-data`; // Local persistence for Typesense.

    // -----------------------------
    // Hosted Zone
    // -----------------------------
    // Look up the existing hosted zone so we can create A records.
    const zone = route53.HostedZone.fromLookup(this, 'Zone', {
      domainName,
    });

    // -----------------------------
    // VPC
    // -----------------------------
    // Public subnet for EC2; isolated private subnet for RDS.
    const vpc = new ec2.Vpc(this, 'Vpc', {
      maxAzs: 2,
      natGateways: 0,
      subnetConfiguration: [
        { name: 'public', subnetType: ec2.SubnetType.PUBLIC },
        { name: 'isolated', subnetType: ec2.SubnetType.PRIVATE_ISOLATED },
      ],
    });

    // -----------------------------
    // Security Groups
    // -----------------------------
    const instanceSg = new ec2.SecurityGroup(this, 'InstanceSg', {
      vpc,
      allowAllOutbound: true,
    });
    // Allow HTTP/HTTPS to the instance.
    instanceSg.addIngressRule(ec2.Peer.anyIpv4(), ec2.Port.tcp(80));
    instanceSg.addIngressRule(ec2.Peer.anyIpv4(), ec2.Port.tcp(443));

    const dbSg = new ec2.SecurityGroup(this, 'DbSg', {
      vpc,
      allowAllOutbound: true,
    });
    // DB only accepts connections from the app instance.
    dbSg.addIngressRule(instanceSg, ec2.Port.tcp(5432));

    // -----------------------------
    // S3
    // -----------------------------
    const bucket = new s3.Bucket(this, 'AppBucket', {
      encryption: s3.BucketEncryption.S3_MANAGED,
      blockPublicAccess: s3.BlockPublicAccess.BLOCK_ALL,
      removalPolicy: cdk.RemovalPolicy.DESTROY,
      autoDeleteObjects: true,
    });

    // -----------------------------
    // RDS
    // -----------------------------
    // Generate DB credentials and store in Secrets Manager.
    const dbSecret = new secretsmanager.Secret(this, 'DbSecret', {
      generateSecretString: {
        secretStringTemplate: JSON.stringify({ username: 'appuser' }),
        generateStringKey: 'password',
        excludePunctuation: true,
      },
    });

    const db = new rds.DatabaseInstance(this, 'Postgres', {
      vpc,
      vpcSubnets: { subnetType: ec2.SubnetType.PRIVATE_ISOLATED },
      engine: rds.DatabaseInstanceEngine.postgres({
        version: rds.PostgresEngineVersion.VER_16,
      }),
      instanceType: ec2.InstanceType.of(ec2.InstanceClass.T4G, ec2.InstanceSize.MICRO),
      credentials: rds.Credentials.fromSecret(dbSecret),
      databaseName: 'evolprod',
      securityGroups: [dbSg],
      allocatedStorage: 20,
      deletionProtection: false,
      deleteAutomatedBackups: true,
      removalPolicy: cdk.RemovalPolicy.DESTROY,
    });

    // -----------------------------
    // CloudWatch Logs
    // -----------------------------
    const jsonLogGroup = new logs.LogGroup(this, 'JsonLogs', {
      logGroupName: `/evolnow/${domainName}/storage-json`,
      retention: logs.RetentionDays.ONE_WEEK,
      removalPolicy: cdk.RemovalPolicy.DESTROY,
    });

    // -----------------------------
    // IAM Role
    // -----------------------------
    const role = new iam.Role(this, 'InstanceRole', {
      assumedBy: new iam.ServicePrincipal('ec2.amazonaws.com'),
    });
    // SSM for access and CloudWatch for logs.
    role.addManagedPolicy(
      iam.ManagedPolicy.fromAwsManagedPolicyName('AmazonSSMManagedInstanceCore'),
    );
    role.addManagedPolicy(
      iam.ManagedPolicy.fromAwsManagedPolicyName('CloudWatchAgentServerPolicy'),
    );

    // SES send permission for outbound email.
    role.addToPolicy(new iam.PolicyStatement({
        actions: ['ses:SendEmail', 'ses:SendRawEmail'],
        resources: ['*'],
    }));

    // Allow instance to read DB secret and access the bucket.
    dbSecret.grantRead(role);
    bucket.grantReadWrite(role);

    // -----------------------------
    // EC2 Instance
    // -----------------------------
    const instance = new ec2.Instance(this, 'WebInstance', {
      vpc,
      instanceType,
      machineImage: ec2.MachineImage.latestAmazonLinux2023({
        cpuType: ec2.AmazonLinuxCpuType.ARM_64,
      }),
      vpcSubnets: { subnetType: ec2.SubnetType.PUBLIC },
      securityGroup: instanceSg,
      role,
    });

    // Static public IP so DNS stays stable.
    const eip = new ec2.CfnEIP(this, 'Eip', { domain: 'vpc' });
    new ec2.CfnEIPAssociation(this, 'EipAssoc', {
      instanceId: instance.instanceId,
      eip: eip.ref,
    });

    new route53.ARecord(this, 'ApexRecord', {
      zone,
      recordName: domainName,
      target: route53.RecordTarget.fromIpAddresses(eip.ref),
    });

    new route53.ARecord(this, 'WwwRecord', {
      zone,
      recordName: `www.${domainName}`,
      target: route53.RecordTarget.fromIpAddresses(eip.ref),
    });

    // -----------------------------
    // SES
    // -----------------------------
    // Setup manually in console for now.
    // new ses.EmailIdentity(this, 'SesDomain', {
    //   identity: ses.Identity.publicHostedZone(zone),
    // });

    // -----------------------------
    // UserData
    // -----------------------------
    const ud = ec2.UserData.forLinux();
    ud.addCommands(
      'set -euxo pipefail',
      'dnf update -y',
      'dnf install -y nginx docker docker-compose-plugin amazon-cloudwatch-agent jq certbot python3-certbot-nginx',
      'systemctl enable --now docker nginx',

      `mkdir -p ${appDir} ${logsDir} ${typesenseDataDir}`,

      // docker-compose (valkey + typesense)
      `cat > ${hostRoot}/docker-compose.yml <<YAML
services:
  redis:
    image: valkey/valkey:alpine
    restart: unless-stopped
    ports: ["127.0.0.1:6379:6379"]

  typesense:
    image: typesense/typesense:0.25.2
    restart: unless-stopped
    command: "--data-dir /data --api-key=masterKey --listen-port 8108 --enable-cors"
    ports: ["127.0.0.1:8108:8108"]
    volumes:
      - ${typesenseDataDir}:/data
YAML`,
      `cd ${hostRoot} && docker compose up -d`,

      // Extract app files from the image to the host.
      'docker rm -f evolnow-extract || true',
      `docker create --name evolnow-extract ${webImage}`,
      `docker cp evolnow-extract:/var/www/html/. ${appDir}/`,
      'docker rm evolnow-extract',

      // Write .env using credentials from Secrets Manager.
      `DB_SECRET=$(aws secretsmanager get-secret-value --secret-id ${dbSecret.secretArn} --query SecretString --output text)`,
      `DB_USER=$(echo "$DB_SECRET" | jq -r .username)`,
      `DB_PASS=$(echo "$DB_SECRET" | jq -r .password)`,

      `cat > ${appDir}/.env <<EOF
APP_NAME=eVol
APP_ENV=production
APP_DEBUG=false
APP_URL=https://${domainName}

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=${db.dbInstanceEndpointAddress}
DB_PORT=5432
DB_DATABASE=evolprod
DB_USERNAME=$DB_USER
DB_PASSWORD=$DB_PASS

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

SCOUT_DRIVER=typesense
TYPESENSE_HOST=127.0.0.1
TYPESENSE_PORT=8108
TYPESENSE_PROTOCOL=http
TYPESENSE_API_KEY=masterKey

MAIL_MAILER=ses
MAIL_FROM_ADDRESS=noreply@${domainName}
MAIL_FROM_NAME="eVol"

FILESYSTEM_DISK=s3
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=${bucket.bucketName}
EOF`,

      // Permissions for Laravel storage/cache.
      `chown -R 33:33 ${appDir}/storage ${appDir}/bootstrap/cache || true`,
      `chmod -R 775 ${appDir}/storage ${appDir}/bootstrap/cache || true`,

      // Run PHP-FPM container with host app mounted.
      'docker rm -f evolnow-app || true',
      `docker run -d --name evolnow-app --restart unless-stopped \
        -p 127.0.0.1:9000:9000 \
        -v ${appDir}:/var/www/html \
        -v ${appDir}/.env:/var/www/html/.env \
        -v ${logsDir}:/var/www/html/storage/logs \
        ${webImage}`,

      // Generate APP_KEY once for Laravel.
      `if ! grep -q "^APP_KEY=" ${appDir}/.env; then docker exec evolnow-app php artisan key:generate --force; fi`,

      // nginx config for the domain.
      `cat > /etc/nginx/conf.d/evolnow.conf <<NGINX
server {
  listen 80;
  server_name ${domainName} www.${domainName};
  root ${appDir}/public;
  index index.php;

  location /.well-known/acme-challenge/ { root /var/www/html; }

  location / { try_files $uri $uri/ /index.php?$query_string; }

  location ~ \\.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass 127.0.0.1:9000;
  }
}
NGINX`,
      'nginx -t && systemctl reload nginx',

      // Let's Encrypt (initial issuance).
      `certbot --nginx -d ${domainName} -d www.${domainName} --non-interactive --agree-tos -m ${letsEncryptEmail} || true`,
      'systemctl reload nginx',

      // CloudWatch Agent for JSON logs.
      `cat > /opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json <<CW
{
  "logs": {
    "logs_collected": {
      "files": {
        "collect_list": [{
          "file_path": "${logsDir}/*.json",
          "log_group_name": "${jsonLogGroup.logGroupName}",
          "log_stream_name": "{instance_id}"
        }]
      }
    }
  }
}
CW`,
      '/opt/aws/amazon-cloudwatch-agent/bin/amazon-cloudwatch-agent-ctl -a fetch-config -m ec2 -c file:/opt/aws/amazon-cloudwatch-agent/etc/amazon-cloudwatch-agent.json -s',

      // Let's Encrypt renewal (cron).
      `cat > /etc/cron.d/certbot-renew <<'CRON'
SHELL=/bin/bash
PATH=/sbin:/bin:/usr/sbin:/usr/bin
0 3 * * * root certbot renew --quiet --deploy-hook "systemctl reload nginx"
CRON`,
      'chmod 644 /etc/cron.d/certbot-renew',
    );

    instance.addUserData(ud.render());

    // -----------------------------
    // Outputs
    // -----------------------------
    new cdk.CfnOutput(this, 'ElasticIp', { value: eip.ref });
    new cdk.CfnOutput(this, 'DbEndpoint', { value: db.dbInstanceEndpointAddress });
    new cdk.CfnOutput(this, 'BucketName', { value: bucket.bucketName });
  }
}
