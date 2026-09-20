# AWS Well-Architected Framework Alignment

This deployment was evaluated against the six pillars of the AWS Well-Architected Framework:

| Pillar | Implementation in This Architecture | Architectural Decision & Production Context |
| :--- | :--- | :--- |
| **Operational Excellence** | Infrastructure defined as code via AWS CloudFormation; zero-bastion management through AWS Systems Manager Session Manager; centralized CloudWatch dashboard monitoring real-time health; automated SNS email alerting. | Workloads run without external SSH access. Metrics and alarms provide proactive alerting on infrastructure anomalies before customer impact. |
| **Security** | Strict defense-in-depth design; AWS WAF OWASP Top 10 rules; ALB origin cloaking via CloudFront prefix lists; security group least-privilege chaining; IMDSv2 token enforcement; zero public IPs on compute and database tiers; closed SSH port 22. | Complete origin shielding guarantees traffic cannot bypass edge inspection. Security group chaining enforces network isolation at every boundary. |
| **Reliability** | Multi-AZ architecture across `us-east-1a` and `us-east-1b`; Auto Scaling Group maintaining desired instance capacity; ALB target health checks; Amazon RDS Multi-AZ synchronous standby replication with automated failover; Route 53 global health checking across 8 regions. | Automated failover at compute, edge, and database tiers eliminates single points of failure for core traffic processing. |
| **Performance Efficiency** | Amazon CloudFront edge caching offloads traffic from origin; Application Load Balancer distributes compute load evenly; burstable T-series compute provides rapid response to dynamic traffic spikes; low-latency sub-5ms target response times. | Static and dynamic assets cached at PoPs globally, reducing origin compute overhead and latency for international clients. |
| **Cost Optimization** | Single-AZ NAT Gateway architecture cuts baseline NAT fees by ~50% (~$32.40/month savings); right-sized `t3.micro` and `db.t3.micro` instances operate within lab thresholds; targeted WAF rule bundles avoid expensive pre-packaged subscriptions; overall design operates under $100/month. | Budget-conscious design choices are clearly identified with transparent paths to full enterprise resilience. |
| **Sustainability** | Modern Graviton-ready instance classes and right-sized T-series burstable compute avoid idle resource consumption; dynamic Auto Scaling scales in unused instances during low-traffic periods. | Resource consumption matches actual workload demand, minimizing unnecessary energy and compute waste. |

---

[← Return to README](../README.md)
