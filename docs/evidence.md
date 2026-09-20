# Verified Deliverables & Empirical Evidence Archive

> [!IMPORTANT]
> **Evidence Status: Verified Live**  
> **Last Verified Date**: `September 20, 2026`  
> All test runs, terminal sessions, metrics, and screenshots in this document were captured directly from the live AWS environment (`production-3tier-web-stack`, `us-east-1`).

---

### Deliverable 1: Solution Architecture Diagram
High-resolution 3-tier architecture diagram displaying multi-AZ subnets, Auto Scaling boundary, origin cloaking, and numbered workflow steps (1-5).

![Architecture Diagram](architecture-diagram.png)

---

### Deliverable 2: Route 53 DNS Configuration
Route 53 Public Hosted Zone (`app.production-aws-lab.com`) routing traffic via an Alias A record directly to CloudFront (`d1topmc0acns1k.cloudfront.net`).

![Route 53 DNS Configuration](evidence/21-route53-dns-alias-record.png)

---

### Deliverable 3: Route 53 Global Health Probing
Route 53 Health Check (`prod-edge-health-check`) actively monitoring the HTTPS endpoint every 30 seconds, reporting healthy (HTTP 200 OK) status across all 8 international edge regions.

![Route 53 Global Health Check Status](evidence/22-route53-global-health-probing.png)

---

### Deliverable 4: AWS WAF Layer 7 Interception & Managed Rules
Testing the CloudFront endpoint with an injected Cross-Site Scripting (XSS) payload demonstrates automated blocking by AWS WAF:
```bash
curl -I "https://d1topmc0acns1k.cloudfront.net/?test=<script>alert(1)</script>"
```
The request is blocked at the edge with an `HTTP/2 403 Forbidden` response from CloudFront without reaching the origin ALB.

![AWS WAF Layer 7 XSS Interception](evidence/19-waf-xss-interception-403.png)

#### AWS WAF Managed Rule Sets Configuration
Web ACL `prod-web-waf` configured with active AWS Managed Rule Sets (`AWS-AWSManagedRulesCommonRuleSet`, `AWS-AWSManagedRulesKnownBadInputsRuleSet`, and `AWS-AWSManagedRulesAmazonIPReputationList`) inspecting requests in ascending priority order:

![AWS WAF Managed Rules Configuration](evidence/16-waf-managed-rules-configuration.png)

---

### Deliverable 5: ALB Origin Cloaking Verification
Direct access to the public Application Load Balancer DNS is blocked by the ALB security group, which enforces the CloudFront Origin-Facing Managed Prefix List:
```bash
curl -I -m 5 http://prod-web-alb-794434403.us-east-1.elb.amazonaws.com
```
The connection times out after 5000 milliseconds, confirming complete isolation from direct internet bypass.

![ALB Origin Cloaking Timeout](evidence/20-alb-origin-cloaking-timeout.png)

---

### Deliverable 6: Auto Scaling Group & Multi-AZ Compute
The Application Load Balancer successfully balances incoming requests across healthy EC2 instances running in separate Availability Zones:
- Response 1 served from instance `i-07d1eed378b003007` in `us-east-1a`.
- Response 2 served from instance `i-0be48c2faa91d94e2` in `us-east-1b`.

| Response from AZ 1a (`us-east-1a`) | Response from AZ 1b (`us-east-1b`) |
| :---: | :---: |
| ![ALB Balanced AZ-1a](evidence/10-alb-balanced-az1a.png) | ![ALB Balanced AZ-1b](evidence/11-alb-balanced-az1b.png) |

#### Launch Template Configuration (`web-app-template`)
Launch template configuration enforcing IMDSv2 (`HttpTokens: required`), IAM role assignment, and zero key pairs.

![EC2 Launch Template](evidence/09-ec2-launch-template.png)

---

### Deliverable 7: Amazon RDS Multi-AZ Topology & Verification
Amazon RDS MySQL instance (`prod-web-db`) configured with Multi-AZ synchronous replication:
```bash
aws rds describe-db-instances \
  --db-instance-identifier prod-web-db \
  --query "DBInstances[0].{Status:DBInstanceStatus, MultiAZ:MultiAZ, PrimaryAZ:AvailabilityZone, SecondaryAZ:SecondaryAvailabilityZone}" \
  --output table
```
CLI output verifies `MultiAZ: True`, Primary node in `us-east-1a`, and Standby replica in `us-east-1b`.

![RDS Multi-AZ CLI Verification](evidence/12-rds-multiaz-cli-verification.png)

#### Database Multi-AZ Conversion Events
RDS event logs confirming successful automated conversion and snapshot creation for Multi-AZ operation.

![RDS Multi-AZ Events](evidence/13-rds-multiaz-events.png)

#### End-to-End Private EC2 to RDS Connectivity
Connecting from a private application instance (`10.0.12.87`) to the MySQL RDS endpoint via the MySQL client, verifying authorized SQL access over port 3306.

![Private EC2 to RDS Connection](evidence/15-private-ec2-to-rds-mysql-terminal.png)

---

### Deliverable 8: Bastionless Systems Manager (SSM) Access
Secure shell session (`root-cqf3n8tqjypdoopxox4dotan6q`) initiated via AWS Systems Manager Session Manager into private instance `i-0be48c2faa91d94e2` without public IPs or open SSH ports.

![SSM Session Manager Access](evidence/14-ssm-session-manager-terminal.png)

---

### Deliverable 9: CloudWatch Operational Dashboard
Single-pane operations view displaying live alarm status banners, ALB throughput, target response times, ASG CPU utilization, and RDS database metrics.

![CloudWatch Operational Dashboard](evidence/24-cloudwatch-operational-dashboard.png)

---

### Deliverable 10: Amazon SNS Alert Subscription
Amazon SNS console showing confirmed email subscription for topic `prod-web-alerts-topic`.

![SNS Email Subscription Confirmed](evidence/23-sns-email-subscription-confirmed.png)

---

### Deliverable 11: CloudWatch Metric Alarms
Top operational health status banner showing all three core alarms (`prod-asg-high-cpu-utilization`, `prod-edge-healthcheck-failed`, and `prod-web-alb-high-5xx-errors`) in healthy OK status.

![CloudWatch Alarms Banner](evidence/24-cloudwatch-operational-dashboard.png)

---

### Additional Verified Supporting Evidence

#### VPC Resource Map & Routing Breakdown
Visual resource map of VPC `prod-web-vpc` demonstrating 6 subnets, 4 route tables, Internet Gateway, and Single NAT Gateway.

![VPC Resource Map](evidence/01-vpc-resource-map.png)

| Public Route Table (`public-rt`) | Private App Route Table (`private-app-rt`) | Private DB Route Table (`private-db-rt`) |
| :---: | :---: | :---: |
| ![Public RT](evidence/02-public-route-table.png) | ![Private App RT](evidence/03-private-app-route-table.png) | ![Private DB RT](evidence/04-private-db-route-table.png) |

#### Security Group Ingress Chaining
Strict least-privilege chaining from ALB to EC2 to RDS.

| ALB Security Group (`alb-sg`) | EC2 Web Security Group (`ec2-web-sg`) | RDS DB Security Group (`rds-db-sg`) |
| :---: | :---: | :---: |
| ![ALB SG](evidence/06-alb-security-group.png) | ![EC2 SG](evidence/07-ec2-web-security-group.png) | ![RDS SG](evidence/08-rds-db-security-group.png) |

#### CloudFront Distribution & Edge SSL
CloudFront distribution `prod-web-cf` serving HTTPS traffic globally with AWS WAF enabled.

| CloudFront Distribution Security | Live HTTPS App via CloudFront |
| :---: | :---: |
| ![CloudFront Config](evidence/17-cloudfront-distribution-waf-attached.png) | ![CloudFront HTTPS](evidence/18-cloudfront-https-app-live.png) |

#### AWS WAF Web ACL & Managed Rule Sets
AWS WAF Web ACL (`prod-web-waf`) with active AWS Managed Rule Sets inspecting incoming traffic in priority order.

![AWS WAF Managed Rules Configuration](evidence/16-waf-managed-rules-configuration.png)

---

[← Return to README](../README.md)
