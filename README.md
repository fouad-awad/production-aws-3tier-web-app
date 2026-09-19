# Production-Grade Highly Available 3-Tier Web Architecture on AWS

[![AWS Architecture](https://img.shields.io/badge/AWS-3--Tier%20Architecture-FF9900?logo=amazon-aws&logoColor=white)](https://aws.amazon.com/)
[![CloudFormation](https://img.shields.io/badge/IaC-AWS%20CloudFormation-orange?logo=amazon-aws&logoColor=white)](https://aws.amazon.com/cloudformation/)
[![Security](https://img.shields.io/badge/Security-Origin%20Cloaking%20%7C%20WAF%20%7C%20SSM-green?logo=shield)](https://aws.amazon.com/waf/)
[![High Availability](https://img.shields.io/badge/Availability-Multi--AZ%20Active--Active-blue)](https://aws.amazon.com/rds/features/multi-az/)
[![Budget Compliant](https://img.shields.io/badge/Lab%20Budget-%3C$100%20Optimized-success)](https://aws.amazon.com/pricing/)
[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](LICENSE)

An enterprise-standard, highly available, secure 3-tier web architecture deployed in `us-east-1` under a strict $100 lab budget constraint. This project demonstrates defense-in-depth security, strict origin isolation, multi-AZ resilience, zero-bastion instance management, and comprehensive observability adhering to the AWS Well-Architected Framework.

---

## Table of Contents
- [Solution Overview](#solution-overview)
- [Architecture Diagram & Traffic Flow](#architecture-diagram--traffic-flow)
- [VPC & Network Topology](#vpc--network-topology)
- [Security Architecture & Origin Cloaking](#security-architecture--origin-cloaking)
- [Compute & Auto Scaling Tier](#compute--auto-scaling-tier)
- [Database Tier (Multi-AZ RDS)](#database-tier-multi-az-rds)
- [Observability & Operational Monitoring](#observability--operational-monitoring)
- [Cost-Optimization Strategy (<$100 Budget)](#cost-optimization-strategy-100-budget)
- [Verified Deliverables & Evidence Archive](#verified-deliverables--evidence-archive)
- [Deployment Guide](#deployment-guide)
- [Verification & Operational Testing](#verification--operational-testing)
- [Teardown & Clean Up](#teardown--clean-up)
- [AWS Well-Architected Framework Alignment](#aws-well-architected-framework-alignment)
- [License](#license)

---

## Solution Overview

Modern enterprise applications require robust fault tolerance, rapid elasticity, strict perimeter defense, and comprehensive operational visibility without incurring unsustainable cloud infrastructure costs.

This solution deploys a production-grade 3-tier web architecture hosting dynamic Apache and PHP workloads backed by an Amazon RDS MySQL database. The infrastructure spans two Availability Zones (`us-east-1a` and `us-east-1b`) to ensure continuous uptime during single-zone disruptions or maintenance windows.

### Core Architectural Highlights
- **Resilient Multi-AZ Compute**: Auto Scaling Group dynamically managing EC2 instances in private subnets across two AZs with automatic health checks and target tracking scaling.
- **Zero-Bastion Fleet Management**: Administrative access is brokered strictly through AWS Systems Manager (SSM) Session Manager. SSH port 22 is disabled across all security groups, eliminating external attack vectors.
- **Perimeter Defense & Origin Cloaking**: Amazon CloudFront terminates client TLS and caches content at edge locations. AWS WAF inspects incoming requests against OWASP Top 10 vulnerabilities. The Application Load Balancer (ALB) enforces origin cloaking via the CloudFront Origin-Facing Managed Prefix List, rejecting direct bypass attempts.
- **Isolated Multi-AZ Data Layer**: Amazon RDS MySQL operates with synchronous replication between primary (`us-east-1b`) and standby (`us-east-1a`) instances. The database resides in isolated subnets with no internet gateways or NAT routes.
- **Proactive Observability**: Amazon CloudWatch dashboard displays end-to-end metrics (ALB requests, latency, ASG CPU utilization, and RDS health) alongside automated CloudWatch Alarms integrated with Amazon SNS email notifications.
- **Cost Engineered (<$100 Lab Budget)**: Utilizes a Single-AZ NAT Gateway routing pattern, burstable `t3.micro` and `db.t3.micro` instances, and targeted AWS WAF rule bundles to maximize security while keeping monthly expenditure well within lab constraints.

---

## Architecture Diagram & Traffic Flow

The architecture implements physical separation between tiers across two Availability Zones, with inbound access flowing strictly through edge defenses.

![Production Solution Architecture Diagram](docs/architecture-diagram.png)

### End-to-End Traffic Walkthrough

```text
[Client / Users]
       │ (1) DNS Resolution & Global Edge Health Checks
       ▼
[Amazon Route 53] (app.production-aws-lab.com -> CloudFront Alias)
       │
       ▼
[AWS WAF] (OWASP Top 10, Bad Inputs, IP Reputation)
       │ (2) Layer 7 Request Inspection & Filtering
       ▼
[Amazon CloudFront] (d1topmc0acns1k.cloudfront.net)
       │ (3) Global Edge Caching & Origin Cloaked Egress
       ▼
[Amazon VPC (10.0.0.0/16)] ─── [Internet Gateway]
       │
       ▼
[Application Load Balancer: prod-web-alb] (Public Subnets 10.0.1.0/24 & 10.0.2.0/24)
       │ (4) Round-Robin Reverse Proxy to Private Compute
       ├─────────────────────────────────────────┐
       ▼                                         ▼
[Private App Subnet 1a]                   [Private App Subnet 1b]
  EC2 App Instance (Apache/PHP)             EC2 App Instance (Apache/PHP)
  Subnet: 10.0.11.0/24                      Subnet: 10.0.12.0/24
       │                                         │
       │ (5) MySQL 3306 Query Traffic            │
       └────────────────────┬────────────────────┘
                            ▼
               [Private DB Subnets 1a & 1b]
               Primary RDS MySQL (10.0.22.0/24)
                      │
                      │ Synchronous Replication
                      ▼
               Standby RDS MySQL (10.0.21.0/24)
```

1. **Step 1: DNS Resolution & Edge Probing**: Users resolve `app.production-aws-lab.com` through Amazon Route 53, which uses an Alias A record pointing directly to CloudFront. Simultaneously, Route 53 Global Health Checks probe the endpoint across 8 international locations.
2. **Step 2: Layer 7 Perimeter Inspection**: Requests pass through AWS WAF attached to CloudFront. Pre-configured managed rule sets evaluate headers and payloads for SQL Injection, Cross-Site Scripting (XSS), bad inputs, and known malicious IPs before requests can proceed.
3. **Step 3: Edge Caching & Origin Cloaked Transit**: CloudFront serves cached assets from edge PoPs. For cache misses or dynamic content, CloudFront routes traffic to the origin ALB. Traffic is verified against the CloudFront Origin-Facing Managed Prefix List.
4. **Step 4: Load Balancing to Private Compute**: The ALB terminates client connections and distributes requests across EC2 instances provisioned in private subnets across `us-east-1a` and `us-east-1b`. Instances process the dynamic PHP application and fetch local metadata via IMDSv2.
5. **Step 5: Secure Multi-AZ Data Persistence**: Application instances communicate over port 3306 with the primary Amazon RDS MySQL instance. Synchronous data replication continuously mirrors state to the Multi-AZ standby replica in the secondary AZ.
6. **Out-of-Band Management & Observability**: Systems administrators access private EC2 instances without bastion hosts or SSH keys using AWS Systems Manager Session Manager. Operational metrics flow into Amazon CloudWatch, triggering SNS email notifications upon anomalous conditions.

---

## VPC & Network Topology

The network configuration separates resources into public, private application, and private data subnets across two Availability Zones (`us-east-1a` and `us-east-1b`).

```text
VPC CIDR: 10.0.0.0/16
├── Availability Zone: us-east-1a
│   ├── public-subnet-1a       : 10.0.1.0/24   (ALB Node 1, Single NAT Gateway)
│   ├── private-app-subnet-1a  : 10.0.11.0/24  (EC2 App Fleet, No Public IPs)
│   └── private-db-subnet-1a   : 10.0.21.0/24  (RDS Multi-AZ Standby Replica)
│
└── Availability Zone: us-east-1b
    ├── public-subnet-1b       : 10.0.2.0/24   (ALB Node 2)
    ├── private-app-subnet-1b  : 10.0.12.0/24  (EC2 App Fleet, No Public IPs)
    └── private-db-subnet-1b   : 10.0.22.0/24  (RDS Multi-AZ Primary Node)
```

### Subnet Allocation Matrix

| Subnet Name | CIDR Block | Availability Zone | Route Table | Gateway / Egress Target | Workload Description |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `public-subnet-1a` | `10.0.1.0/24` | `us-east-1a` | `public-rt` | Internet Gateway (`prod-web-igw`) | ALB ingress endpoint and Single-AZ NAT Gateway (`prod-web-nat-1a`). |
| `public-subnet-1b` | `10.0.2.0/24` | `us-east-1b` | `public-rt` | Internet Gateway (`prod-web-igw`) | ALB secondary ingress endpoint for high availability. |
| `private-app-subnet-1a` | `10.0.11.0/24` | `us-east-1a` | `private-app-rt` | NAT Gateway (`prod-web-nat-1a`) | Private EC2 application tier instances (no public IPs). |
| `private-app-subnet-1b` | `10.0.12.0/24` | `us-east-1b` | `private-app-rt` | NAT Gateway (`prod-web-nat-1a`) | Private EC2 application tier instances with cross-AZ NAT egress. |
| `private-db-subnet-1a` | `10.0.21.0/24` | `us-east-1a` | `private-db-rt` | Isolated (No Internet Gateway / NAT) | RDS MySQL Multi-AZ standby replica. |
| `private-db-subnet-1b` | `10.0.22.0/24` | `us-east-1b` | `private-db-rt` | Isolated (No Internet Gateway / NAT) | RDS MySQL Multi-AZ primary database instance. |

### Routing Table Design

1. **`public-rt` (Public Route Table)**:
   - `10.0.0.0/16` -> `local`
   - `0.0.0.0/0` -> `prod-web-igw` (Internet Gateway)
   - Associated Subnets: `public-subnet-1a`, `public-subnet-1b`
2. **`private-app-rt` (Private Application Route Table)**:
   - `10.0.0.0/16` -> `local`
   - `0.0.0.0/0` -> `prod-web-nat-1a` (NAT Gateway in AZ-1a)
   - Associated Subnets: `private-app-subnet-1a`, `private-app-subnet-1b`
3. **`private-db-rt` (Private Database Route Table)**:
   - `10.0.0.0/16` -> `local`
   - No default route (`0.0.0.0/0`), preventing database egress or ingress to the outside world.
   - Associated Subnets: `private-db-subnet-1a`, `private-db-subnet-1b`

---

## Security Architecture & Origin Cloaking

Defense-in-depth is enforced across all layers of the architecture, from global edge inspection down to database port authorization.

```text
[Public Internet]
       │
       ▼
[AWS WAF + CloudFront]
       │ Only requests with CloudFront Managed Prefix List
       ▼
[Security Group: alb-sg]
       │ Only Port 80/443 from alb-sg
       ▼
[Security Group: ec2-web-sg]
       │ Only Port 3306 from ec2-web-sg
       ▼
[Security Group: rds-db-sg]
```

### 1. Security Group Chaining Matrix

| Security Group | Attached Resource | Direction | Protocol | Port Range | Source / Destination | Security Rationale |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **`alb-sg`** | Application Load Balancer | Inbound | TCP | 80, 443 | `com.amazonaws.global.cloudfront.origin-facing` | Restricts incoming traffic exclusively to CloudFront PoPs (Origin Cloaking). Direct public traffic times out. |
| **`alb-sg`** | Application Load Balancer | Outbound | TCP | 80 | `ec2-web-sg` | Permits reverse-proxy forwarding exclusively to the private web application instances. |
| **`ec2-web-sg`** | Auto Scaling EC2 Fleet | Inbound | TCP | 80 | `alb-sg` | Authorizes web traffic originating strictly from the ALB. Direct connections are dropped. |
| **`ec2-web-sg`** | Auto Scaling EC2 Fleet | Outbound | TCP | 3306 | `rds-db-sg` | Permits database queries strictly to the authorized database security group. |
| **`rds-db-sg`** | RDS Multi-AZ Database | Inbound | TCP | 3306 | `ec2-web-sg` | Allows database connections exclusively from application instances. No public or bastion access. |
| **`rds-db-sg`** | RDS Multi-AZ Database | Outbound | - | - | None | Database instances cannot initiate outbound external traffic. |

### 2. Origin Cloaking (CloudFront Shielding)
To prevent attackers from bypassing CloudFront and AWS WAF by sending direct HTTP requests to the public ALB DNS (`prod-web-alb-794434403.us-east-1.elb.amazonaws.com`), the ALB security group restricts ingress to the AWS CloudFront Origin-Facing Managed Prefix List. Any direct curl or browser request attempting to reach the ALB directly times out after 5 seconds (`curl: (28) Connection timed out`).

### 3. Zero-Bastion Management (AWS Systems Manager)
Traditional bastion hosts introduce maintenance overhead, public IP exposure, and SSH key management challenges. In this architecture:
- SSH port 22 is closed across all security groups.
- No EC2 instances have public IP addresses assigned.
- EC2 instances assume an IAM role with the `AmazonSSMManagedInstanceCore` managed policy.
- Engineers connect directly to private instances via browser-based or CLI-based AWS Systems Manager Session Manager sessions, with full session logging and auditability.

### 4. Instance Metadata Service Version 2 (IMDSv2)
To mitigate SSRF (Server-Side Request Forgery) vulnerabilities and credential exfiltration, the EC2 Launch Template enforces IMDSv2 (`HttpTokens: required`). Metadata requests require a signed session token created via an HTTP `PUT` request with an expiration header.

### 5. AWS WAF Layer 7 Filtering
AWS WAF is attached directly to the Amazon CloudFront distribution, evaluating incoming requests using AWS Managed Rule Sets:
- **`AWSManagedRulesCommonRuleSet`**: Baseline protection against common web threats including OWASP Top 10 risks.
- **`AWSManagedRulesKnownBadInputsRuleSet`**: Blocks request patterns known to exploit application vulnerabilities or invalid request formats.
- **`AWSManagedRulesAmazonIpReputationList`**: Intercepts requests from IP addresses associated with botnets and reconnaissance activity.

---

## Compute & Auto Scaling Tier

The compute layer is engineered for elasticity and high availability.

- **Auto Scaling Group (`web-app-asg`)**:
  - Capacity: Desired: 2, Min: 2, Max: 4.
  - Subnet Distribution: Spread across `private-app-subnet-1a` and `private-app-subnet-1b`.
  - Health Check Type: Elastic Load Balancing (`ELB`) with a 300-second grace period.
- **Launch Template (`web-app-template` / `lt-0506b139e3c63efae`)**:
  - AMI: Amazon Linux 2023 (`ami-0e34b50e714a297f1`).
  - Instance Type: `t3.micro` (2 vCPU, 1 GiB RAM).
  - Key Pair: None (enforcing bastion-free SSM access).
  - IAM Profile: `prod-ec2-ssm-instance-profile`.
- **Target Tracking Scaling Policy**:
  - Metric: `ASGAverageCPUUtilization`.
  - Target: 70% average CPU utilization.
  - Action: Dynamically scales out instances when load rises and scales in when demand subsides.

---

## Database Tier (Multi-AZ RDS)

- **Database Engine**: Amazon RDS for MySQL (Engine version: `8.4.9`).
- **Instance Class**: `db.t3.micro` with General Purpose SSD (`gp3`) storage.
- **Multi-AZ Deployment**: Enabled (`MultiAZ: True`).
  - **Primary Node**: Deployed in `us-east-1b` handling active read and write operations.
  - **Standby Replica**: Deployed in `us-east-1a` receiving continuous synchronous replication.
- **Automated Failover**: In the event of primary instance failure or AZ degradation, RDS automatically updates DNS records to promote the standby replica to primary with zero manual intervention.
- **Network Isolation**: Deployed in `prod-web-db-subnet-group` spanning `private-db-subnet-1a` and `private-db-subnet-1b`. `PubliclyAccessible` is set to `false`.

---

## Observability & Operational Monitoring

A single-pane-of-glass operations architecture provides visibility into infrastructure health.

![Operational CloudWatch Dashboard](docs/evidence/24-cloudwatch-operational-dashboard.png)

### 1. Single-Pane CloudWatch Dashboard (`prod-web-operational-dashboard`)
The dashboard aggregates operational telemetry across all tiers:
- **Production Operational Health Status**: Top banner showing live alarm states for all core thresholds.
- **ALB Traffic Throughput & Error Distribution**: Tracks `RequestCount`, `HTTPCode_Target_2XX_Count`, and `HTTPCode_ELB_5XX_Count`.
- **Average Target Response Time**: Real-time ELB latency metrics (consistently sub-5ms).
- **ASG Cluster Average CPU Utilization**: Fleet-wide average CPU usage tracking load and scaling readiness.
- **RDS DB CPU & Connection Pool**: Monitors database CPU utilization and active client connections.

### 2. CloudWatch Metric Alarms & SNS Alerting

| Alarm Identifier | Monitored Metric | Namespace | Threshold | Evaluation Period | Severity | Action |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **`prod-web-alb-high-5xx-errors`** | `HTTPCode_ELB_5XX_Count` | `AWS/ApplicationELB` | $\ge 5$ errors | 1 period (1 min) | High | Publishes alert to `prod-web-alerts-topic`. |
| **`prod-asg-high-cpu-utilization`** | `CPUUtilization` | `AWS/EC2` | $\ge 70\%$ | 2 consecutive periods (2 min) | Medium | Alerts operators and initiates ASG scale-out. |
| **`prod-edge-healthcheck-failed`** | `HealthCheckStatus` | `AWS/Route53` | $< 1$ | 1 period (1 min) | Critical | Alerts team of global edge failure. |

All alarms route to the Amazon SNS topic `prod-web-alerts-topic`, which broadcasts email alerts to subscribed engineers (`fouad.ai@outlook.com`).

---

## Cost-Optimization Strategy (<$100 Budget)

To deploy an enterprise-grade multi-tier architecture while strictly respecting the $100 lab budget constraint, the solution applies key cost engineering principles:

```text
Estimated Monthly Cost Breakdown (<$100 Lab Target)
┌──────────────────────────────────────────┬─────────────────┐
│ Service Component                        │ Estimated Cost  │
├──────────────────────────────────────────┼─────────────────┤
│ Single-AZ NAT Gateway (AZ-1a)            │ ~$32.40 / month │
│ EC2 Fleet (2x t3.micro ASG baseline)     │ ~$15.18 / month │
│ RDS MySQL Multi-AZ (db.t3.micro)         │ ~$25.50 / month │
│ Application Load Balancer (prod-web-alb) │ ~$16.20 / month │
│ AWS WAF (Web ACL + 3 Managed Rules)      │ ~$6.00 / month  │
│ Route 53 (Hosted Zone + Health Check)    │ ~$1.25 / month  │
│ CloudFront + CloudWatch + SNS (Tier)     │ ~$1.50 / month  │
├──────────────────────────────────────────┼─────────────────┤
│ Total Estimated Operating Cost           │ ~$98.03 / month │
└──────────────────────────────────────────┴─────────────────┘
```

### Key Cost Optimizations
1. **Single-AZ NAT Gateway Architecture**: Running NAT Gateways across multiple AZs incurs ~$32.40/month per gateway plus cross-AZ data fees. By placing a single NAT Gateway in `public-subnet-1a` and routing outbound traffic from `private-app-subnet-1b` through it, baseline NAT expenses were cut by 50% (~$32.40/month saved).
2. **Right-Sized Burstable Compute**: Deploying `t3.micro` EC2 instances and `db.t3.micro` RDS instances provides sufficient baseline performance with CPU credit bursting while remaining within free-tier or micro-tier pricing.
3. **Targeted WAF Rule Selection**: Using a custom-built Web ACL bundle with AWS Managed Rules ($14/10M requests) instead of comprehensive pre-packaged bundles ($43-$59/month) delivers OWASP Top 10 protection at a fraction of the cost.
4. **Bastion-Free Design**: Eliminates the dedicated EC2 instance, Elastic IP, and maintenance charges associated with traditional bastion jump hosts.

---

## Verified Deliverables & Evidence Archive

The implementation was validated live through end-to-end testing. The sections below showcase the empirical evidence for each architectural deliverable.

---

### Deliverable 1: Solution Architecture Diagram
High-resolution 3-tier architecture diagram displaying multi-AZ subnets, Auto Scaling boundary, origin cloaking, and numbered workflow steps (1-5).

![Architecture Diagram](docs/architecture-diagram.png)

---

### Deliverable 2: Route 53 DNS Configuration
Route 53 Public Hosted Zone (`app.production-aws-lab.com`) routing traffic via an Alias A record directly to CloudFront (`d1topmc0acns1k.cloudfront.net`).

![Route 53 DNS Configuration](docs/evidence/21-route53-dns-alias-record.png)

---

### Deliverable 3: Route 53 Global Health Probing
Route 53 Health Check (`prod-edge-health-check`) actively monitoring the HTTPS endpoint every 30 seconds, reporting healthy (HTTP 200 OK) status across all 8 international edge regions.

![Route 53 Global Health Check Status](docs/evidence/22-route53-global-health-probing.png)

---

### Deliverable 4: AWS WAF Layer 7 Interception
Testing the CloudFront endpoint with an injected Cross-Site Scripting (XSS) payload demonstrates automated blocking by AWS WAF:
```bash
curl -I "https://d1topmc0acns1k.cloudfront.net/?test=<script>alert(1)</script>"
```
The request is blocked at the edge with an `HTTP/2 403 Forbidden` response from CloudFront without reaching the origin ALB.

![AWS WAF Layer 7 XSS Interception](docs/evidence/19-waf-xss-interception-403.png)

---

### Deliverable 5: ALB Origin Cloaking Verification
Direct access to the public Application Load Balancer DNS is blocked by the ALB security group, which enforces the CloudFront Origin-Facing Managed Prefix List:
```bash
curl -I -m 5 http://prod-web-alb-794434403.us-east-1.elb.amazonaws.com
```
The connection times out after 5000 milliseconds, confirming complete isolation from direct internet bypass.

![ALB Origin Cloaking Timeout](docs/evidence/20-alb-origin-cloaking-timeout.png)

---

### Deliverable 6: Auto Scaling Group & Multi-AZ Compute
The Application Load Balancer successfully balances incoming requests across healthy EC2 instances running in separate Availability Zones:
- Response 1 served from instance `i-07d1eed378b003007` in `us-east-1a`.
- Response 2 served from instance `i-0be48c2faa91d94e2` in `us-east-1b`.

| Response from AZ 1a (`us-east-1a`) | Response from AZ 1b (`us-east-1b`) |
| :---: | :---: |
| ![ALB Balanced AZ-1a](docs/evidence/10-alb-balanced-az1a.png) | ![ALB Balanced AZ-1b](docs/evidence/11-alb-balanced-az1b.png) |

#### Launch Template Configuration (`web-app-template`)
Enforcing `t3.micro`, IMDSv2 metadata handling, and zero key pairs (bastionless).

![EC2 Launch Template](docs/evidence/09-ec2-launch-template.png)

---

### Deliverable 7: Amazon RDS Multi-AZ Topology & Verification
Amazon RDS MySQL instance (`prod-web-db`) configured with Multi-AZ synchronous replication:
```bash
aws rds describe-db-instances \
  --db-instance-identifier prod-web-db \
  --query "DBInstances[0].{Status:DBInstanceStatus, MultiAZ:MultiAZ, PrimaryAZ:AvailabilityZone, SecondaryAZ:SecondaryAvailabilityZone}" \
  --output table
```
CLI output verifies `MultiAZ: True`, Primary node in `us-east-1b`, and Standby replica in `us-east-1a`.

![RDS Multi-AZ CLI Verification](docs/evidence/12-rds-multiaz-cli-verification.png)

#### Database Multi-AZ Conversion Events
RDS event logs confirming successful automated conversion and snapshot creation for Multi-AZ operation.

![RDS Multi-AZ Events](docs/evidence/13-rds-multiaz-events.png)

#### End-to-End Private EC2 to RDS Connectivity
Connecting from a private application instance (`10.0.12.87`) to the MySQL RDS endpoint via the MySQL client, verifying authorized SQL access over port 3306.

![Private EC2 to RDS Connection](docs/evidence/15-private-ec2-to-rds-mysql-terminal.png)

---

### Deliverable 8: Bastionless Systems Manager (SSM) Access
Secure shell session (`root-cqf3n8tqjypdoopxox4dotan6q`) initiated via AWS Systems Manager Session Manager into private instance `i-0be48c2faa91d94e2` without public IPs or open SSH ports.

![SSM Session Manager Access](docs/evidence/14-ssm-session-manager-terminal.png)

---

### Deliverable 9: CloudWatch Operational Dashboard
Single-pane operations view displaying live alarm status banners, ALB throughput, target response times, ASG CPU utilization, and RDS database metrics.

![CloudWatch Operational Dashboard](docs/evidence/24-cloudwatch-operational-dashboard.png)

---

### Deliverable 10: Amazon SNS Alert Subscription
Amazon SNS console showing confirmed email subscription for topic `prod-web-alerts-topic`.

![SNS Email Subscription Confirmed](docs/evidence/23-sns-email-subscription-confirmed.png)

---

### Deliverable 11: CloudWatch Metric Alarms
Top operational health status banner showing all three core alarms (`prod-asg-high-cpu-utilization`, `prod-edge-healthcheck-failed`, and `prod-web-alb-high-5xx-errors`) in healthy OK status.

![CloudWatch Alarms Banner](docs/evidence/24-cloudwatch-operational-dashboard.png)

---

### Additional Verified Supporting Evidence

#### VPC Resource Map & Routing Breakdown
Visual resource map of VPC `prod-web-vpc` demonstrating 6 subnets, 4 route tables, Internet Gateway, and Single NAT Gateway.

![VPC Resource Map](docs/evidence/01-vpc-resource-map.png)

| Public Route Table (`public-rt`) | Private App Route Table (`private-app-rt`) | Private DB Route Table (`private-db-rt`) |
| :---: | :---: | :---: |
| ![Public RT](docs/evidence/02-public-route-table.png) | ![Private App RT](docs/evidence/03-private-app-route-table.png) | ![Private DB RT](docs/evidence/04-private-db-route-table.png) |

#### Security Group Ingress Chaining
Strict least-privilege chaining from ALB to EC2 to RDS.

| ALB Security Group (`alb-sg`) | EC2 Web Security Group (`ec2-web-sg`) | RDS DB Security Group (`rds-db-sg`) |
| :---: | :---: | :---: |
| ![ALB SG](docs/evidence/06-alb-security-group.png) | ![EC2 SG](docs/evidence/07-ec2-web-security-group.png) | ![RDS SG](docs/evidence/08-rds-db-security-group.png) |

#### CloudFront Distribution & Edge SSL
CloudFront distribution `prod-web-cf` serving HTTPS traffic globally with AWS WAF enabled.

| CloudFront Distribution Security | Live HTTPS App via CloudFront |
| :---: | :---: |
| ![CloudFront Config](docs/evidence/17-cloudfront-distribution-waf-attached.png) | ![CloudFront HTTPS](docs/evidence/18-cloudfront-https-app-live.png) |

---

## Deployment Guide

### Prerequisites
- AWS Account with Administrator or PowerUser IAM permissions.
- [AWS CLI v2](https://aws.amazon.com/cli/) installed and configured (`aws configure`).
- An active Route 53 Public Hosted Zone for custom domain routing.

### Step 1: Clone the Repository
```bash
git clone https://github.com/your-username/scalable-web-app-aws.git
cd scalable-web-app-aws
```

### Step 2: Deploy Infrastructure via AWS CloudFormation
Deploy the core networking, security groups, database, compute fleet, load balancer, and alarms:

```bash
aws cloudformation deploy \
  --template-file deployment/cloudformation/production-3tier-architecture.yaml \
  --stack-name production-3tier-web-stack \
  --parameter-overrides \
      EnvironmentName=prod \
      VpcCIDR=10.0.0.0/16 \
      DBUsername=admin \
      DBPassword="YourSecureMasterPassword123!" \
      AlertEmail="your-email@domain.com" \
  --capabilities CAPABILITY_NAMED_IAM \
  --region us-east-1
```

### Step 3: Configure CloudFront & WAF
1. Create a CloudFront Distribution pointing to the `ALBDNSName` output from the CloudFormation stack.
2. Configure AWS WAF with `AWSManagedRulesCommonRuleSet`, `AWSManagedRulesKnownBadInputsRuleSet`, and `AWSManagedRulesAmazonIpReputationList`.
3. Associate the Web ACL with the CloudFront distribution.
4. Update the ALB security group (`alb-sg`) to restrict HTTP/HTTPS ingress strictly to the CloudFront Origin-Facing Managed Prefix List (`com.amazonaws.global.cloudfront.origin-facing`).

### Step 4: Configure Route 53 DNS & Health Checks
1. In your Route 53 Hosted Zone, create an Alias A record targeting the CloudFront distribution domain (`d1topmc0acns1k.cloudfront.net`).
2. Create an HTTPS Route 53 Health Check monitoring the domain at 30-second intervals across global health checking regions.

---

## Verification & Operational Testing

### 1. Test ALB Load Balancing & Round-Robin Routing
```bash
# Query the CloudFront endpoint multiple times to verify traffic distribution
for i in {1..4}; do curl -s https://app.production-aws-lab.com | grep -E "Instance ID|Availability Zone"; done
```

### 2. Verify Origin Cloaking (ALB Direct Ingress Drop)
```bash
# Attempt to reach the ALB DNS directly (must time out)
curl -I -m 5 http://<ALB-DNS-NAME>
# Expected output: curl: (28) Connection timed out after 5000 milliseconds
```

### 3. Verify AWS WAF Layer 7 XSS Protection
```bash
# Send an XSS probe to verify WAF interception
curl -I "https://app.production-aws-lab.com/?test=<script>alert(1)</script>"
# Expected output: HTTP/2 403 Forbidden
```

### 4. Connect to Private EC2 via AWS Systems Manager
```bash
# Connect securely without SSH keys or open port 22
aws ssm start-session --target <EC2-INSTANCE-ID> --region us-east-1
```

### 5. Verify Database Connectivity from Private App Tier
From within the SSM session on an EC2 instance:
```bash
mysql -h <RDS-ENDPOINT> -u admin -p
# Run SQL verification
SHOW DATABASES;
```

---

## Teardown & Clean Up

To decommission all resources and avoid ongoing charges:

```bash
# 1. Delete CloudFront Distribution (Disable first, then delete)
aws cloudfront get-distribution-config --id <DISTRIBUTION-ID> > cf-config.json
# Set Enabled to false, update distribution, wait for Deployed, then:
aws cloudfront delete-distribution --id <DISTRIBUTION-ID> --if-match <ETAG>

# 2. Delete AWS WAF Web ACL
aws wafv2 delete-web-acl --name prod-web-waf --scope CLOUDFRONT --id <WAF-ID> --lock-token <TOKEN> --region us-east-1

# 3. Delete Route 53 Records and Health Check
aws route53 delete-health-check --health-check-id <HEALTH-CHECK-ID>

# 4. Delete the CloudFormation Stack
aws cloudformation delete-stack --stack-name production-3tier-web-stack --region us-east-1

# 5. Confirm Stack Deletion
aws cloudformation wait stack-delete-complete --stack-name production-3tier-web-stack --region us-east-1
```

---

## AWS Well-Architected Framework Alignment

| Pillar | Architectural Implementation in this Solution |
| :--- | :--- |
| **Operational Excellence** | Infrastructure defined as code via AWS CloudFormation; zero-bastion management through AWS Systems Manager Session Manager; centralized CloudWatch dashboard monitoring real-time health; automated SNS email alerting. |
| **Security** | Strict defense-in-depth design; AWS WAF OWASP Top 10 rules; ALB origin cloaking via CloudFront prefix lists; security group least-privilege chaining; IMDSv2 token enforcement; zero public IPs on compute and database tiers; closed SSH port 22. |
| **Reliability** | Multi-AZ architecture across `us-east-1a` and `us-east-1b`; Auto Scaling Group maintaining desired instance capacity; ALB target health checks; Amazon RDS Multi-AZ synchronous standby replication with automated failover; Route 53 global health checking across 8 regions. |
| **Performance Efficiency** | Amazon CloudFront edge caching offloads traffic from origin; Application Load Balancer distributes compute load evenly; burstable T-series compute provides rapid response to dynamic traffic spikes; low-latency sub-5ms target response times. |
| **Cost Optimization** | Single-AZ NAT Gateway architecture cuts baseline NAT fees by ~50% (~$32.40/month savings); right-sized `t3.micro` and `db.t3.micro` instances operate within lab thresholds; targeted WAF rule bundles avoid expensive pre-packaged subscriptions; overall design operates under $100/month. |
| **Sustainability** | Modern Graviton-ready instance classes and right-sized T-series burstable compute avoid idle resource consumption; dynamic Auto Scaling scales in unused instances during low-traffic periods. |

---

## License

This project is licensed under the Apache 2.0 License. See the [LICENSE](LICENSE) file for details.
