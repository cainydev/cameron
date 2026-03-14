# Module: Account Architecture & Campaign Lifecycle
## Fetch Condition: Campaign restructuring, segmentation decisions, new campaign creation, or architectural diagnosis

### Consolidation Philosophy
In the Smart Bidding era, over-segmentation is structurally lethal. It starves algorithms of data density. Segment ONLY to provide business context algorithms cannot deduce: margin tiers, seasonal velocity, price competitiveness.

### The 100-Conversion Rule (Strict Enforcement)
- **Peak Efficiency Threshold**: 100+ conversions/month per campaign
- **High-Performance Zone**: 150+ conversions/month = reliable tROAS achievement
- **Volatility Zone**: &lt;30 conversions/month = unpredictable performance, frequent target misses
- **Action**: Consolidate if sub-campaigns cannot sustain 100+ conversions; segment only when data velocity permits

### Custom Label Strategy (5 Dimensions)

| Label | Segmentation Logic | Strategic Application |
|-------|-------------------|---------------------|
| **Profit Margin Tier** | High (&gt;60%), Medium (30-59%), Low (&lt;30%) | Aggressive low-tROAS for high-margin (market share); strict defensive targets for low-margin (profit protection) |
| **Performance Cluster** | Winners (Top 20%), Core (Middle 60%), Bleeders (Bottom 20%) | Budget allocation: 50% Winners, 35% Core, 15% Testing |
| **Price Competitiveness** | Cheaper vs Market, Average, Premium | Increase impression share where pricing advantage exists |
| **Seasonal Velocity** | Q4_Holiday, Summer_Core, etc. | Preemptive budget reallocation and tROAS relaxation before volume spikes |
| **Price Point Bucket** | &lt;$50, $50-$150, &gt;$150 | Prevent $15 accessories competing against $1,500 products in auction dynamics |

### Campaign Creation Triggers

**Hero Product Spin-Out**
- **Criteria**: ROAS &gt;3:1 vs account average, 5+ consistent conversions/week, losing impression share to budget constraints
- **Action**: Isolate to dedicated Standard Shopping/Search campaign
- **Bid Strategy**: 20-50% higher bids than baseline without inflating parent campaign CPA

**Brand/Non-Brand Separation (Mandatory)**
- Branded search converts 2-3x higher than prospecting
- **Never combine**: Algorithm will funnel all budget to brand demand, starving new customer acquisition
- Structural separation required at campaign level

### Campaign Deletion Criteria (Hard Reset)

Diagnose "algorithmic pollution" — when historical data actively works against future success:
- Months optimizing toward low-quality, top-of-funnel clicks
- Broken conversion tracking history
- Prolonged intent mismatch
- **Symptom**: Imposing aggressive tROAS causes campaign to choke/stop spending rather than optimize
- **Action**: Permanent sunset + fresh architectural entity with clean parameters, rigid exclusions, new learning phase

### Budget Allocation Framework
- **50%** → Top Performers (proven winners, scale aggressively)
- **35%** → Core Products (baseline revenue maintenance)
- **15%** → Testing/Exploration (new inventory, hidden gems)

### Structural Red Flags
- [ ] Single Keyword Ad Groups (SKAGs) — obsolete, fragment data
- [ ] Campaigns with &lt;30 conversions/month — consolidate immediately
- [ ] Combined brand + non-brand traffic — separate immediately
- [ ] No custom label utilization — implement margin/performance tiering
