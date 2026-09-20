# VPC & Network Topology

The network configuration establishes a resilient, isolated multi-tier VPC across two Availability Zones (`us-east-1a` and `us-east-1b`) within the `10.0.0.0/16` CIDR space.

```text
VPC CIDR: 10.0.0.0/16
├── Availability Zone: us-east-1a
│   ├── public-subnet-1a       : 10.0.1.0/24   (ALB Node 1, Single NAT Gateway)
│   ├── private-app-subnet-1a  : 10.0.11.0/24  (EC2 App Fleet, No Public IPs)
│   └── private-db-subnet-1a   : 10.0.21.0/24  (RDS Multi-AZ Primary Node)
│
└── Availability Zone: us-east-1b
    ├── public-subnet-1b       : 10.0.2.0/24   (ALB Node 2)
    ├── private-app-subnet-1b  : 10.0.12.0/24  (EC2 App Fleet, No Public IPs)
    └── private-db-subnet-1b   : 10.0.22.0/24  (RDS Multi-AZ Standby Replica)
```

---

## Subnet Allocation Matrix

| Subnet Name | CIDR Block | Availability Zone | Route Table | Gateway / Egress Target | Workload Description |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `public-subnet-1a` | `10.0.1.0/24` | `us-east-1a` | `public-rt` | Internet Gateway (`prod-web-igw`) | ALB ingress endpoint and Single-AZ NAT Gateway (`prod-web-nat-1a`). |
| `public-subnet-1b` | `10.0.2.0/24` | `us-east-1b` | `public-rt` | Internet Gateway (`prod-web-igw`) | ALB secondary ingress endpoint for high availability. |
| `private-app-subnet-1a` | `10.0.11.0/24` | `us-east-1a` | `private-app-rt` | NAT Gateway (`prod-web-nat-1a`) | Private EC2 application tier instances (no public IPs). |
| `private-app-subnet-1b` | `10.0.12.0/24` | `us-east-1b` | `private-app-rt` | NAT Gateway (`prod-web-nat-1a`) | Private EC2 application tier instances with cross-AZ NAT egress. |
| `private-db-subnet-1a` | `10.0.21.0/24` | `us-east-1a` | `private-db-rt` | Isolated (No Internet Gateway / NAT) | RDS MySQL Multi-AZ primary database instance. |
| `private-db-subnet-1b` | `10.0.22.0/24` | `us-east-1b` | `private-db-rt` | Isolated (No Internet Gateway / NAT) | RDS MySQL Multi-AZ standby replica. |

---

## Routing Table Design

1. **`public-rt` (Public Route Table)**:
   - `10.0.0.0/16` -> `local`
   - `0.0.0.0/0` -> `prod-web-igw` (Internet Gateway)
   - Associated Subnets: `public-subnet-1a`, `public-subnet-1b`
   - Purpose: Routes edge ingress traffic directly to the Application Load Balancer nodes and provides outbound connectivity for the NAT Gateway Elastic IP.

2. **`private-app-rt` (Private Application Route Table)**:
   - `10.0.0.0/16` -> `local`
   - `0.0.0.0/0` -> `prod-web-nat-1a` (NAT Gateway in `public-subnet-1a`)
   - Associated Subnets: `private-app-subnet-1a`, `private-app-subnet-1b`
   - Purpose: Authorizes egress-only outbound internet connectivity for software package updates (`dnf update`), security patches, and AWS Systems Manager Session Manager agent communications. Dropped ingress ensures instances have zero public routability.

3. **`private-db-rt` (Isolated Database Route Table)**:
   - `10.0.0.0/16` -> `local`
   - Associated Subnets: `private-db-subnet-1a`, `private-db-subnet-1b`
   - Purpose: Strict network air-gapping. No routes to Internet Gateways, NAT Gateways, or VPC endpoints exist. Database traffic is isolated strictly to intra-VPC MySQL port 3306 queries initiated from the application tier.

---

> [!NOTE]
> **Production Note — Single-AZ NAT Gateway Architecture**:  
> In this implementation, both `private-app-subnet-1a` and `private-app-subnet-1b` route outbound egress through a Single-AZ NAT Gateway (`prod-web-nat-1a`) located in `us-east-1a`. This was a deliberate architectural decision to respect the strict <$100/month lab budget, eliminating ~$32.40/month in baseline NAT charges.  
>  
> However, this introduces a single point of failure (SPOF) for outbound Internet egress: if `us-east-1a` suffers an outage, private compute instances in `us-east-1b` lose external egress (e.g., OS updates, external API calls), even though inbound traffic continues via the Multi-AZ ALB. In an unconstrained production environment, an enterprise deployment would provision one NAT Gateway per Availability Zone (`prod-web-nat-1a` in `us-east-1a` and `prod-web-nat-1b` in `us-east-1b`), isolating AZ fault domains at ~2x the NAT infrastructure cost.

---

[← Return to README](../README.md)
