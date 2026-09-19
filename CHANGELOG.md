# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-09-20

### Added
- Initial release of the Production-Grade Highly Available 3-Tier Web Architecture on AWS.
- Multi-AZ VPC deployment spanning `us-east-1a` and `us-east-1b` with 6 dedicated subnets.
- Cost-optimized Single-AZ NAT Gateway routing architecture saving ~50% baseline NAT fees.
- Internet-facing Application Load Balancer (`prod-web-alb`) with Origin Cloaking via CloudFront prefix list.
- Private EC2 Auto Scaling Group (`web-app-asg`) with dynamic IMDSv2 metadata handling and target tracking scaling.
- Zero-bastion administration via AWS Systems Manager (SSM) Session Manager (SSH port 22 closed globally).
- Amazon RDS MySQL 8.4 Multi-AZ synchronous replication with automatic failover support.
- Amazon CloudFront global content delivery distribution with HTTPS enforcement.
- AWS WAF v2 integration with AWSManagedRulesCommonRuleSet, KnownBadInputsRuleSet, and AmazonIpReputationList.
- Route 53 public DNS alias routing and global edge health checking across 8 international locations.
- End-to-end observability: Amazon SNS email alerting and CloudWatch operational dashboard with metric alarms.
