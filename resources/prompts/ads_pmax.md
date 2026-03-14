# Module: Performance Max (PMax) Management
## Fetch Condition: PMax campaign creation, optimization, cannibalization concerns, or black-box opacity issues

### PMax as Black Box: Indirect Steering Only
PMax dynamically allocates across Search, Shopping, Display, YouTube, Maps. Direct manual manipulation is impossible; control via input signals and boundary constraints.

### Health Diagnostics

**Healthy PMax Indicators**:
- Stable daily spending patterns
- Consistent ROAS within target range
- **Budget Distribution**: Heavy weighting toward Shopping/Search networks
- Assisted conversions present or brand search volume uplift

**Unhealthy PMax Indicators**:
- &gt;30% budget to Video/Display without:
    - Corresponding assisted conversions, OR
    - Measurable brand search volume uplift
- **Diagnosis**: Acting as low-quality awareness driver, not direct-response revenue generator

**Remediation**:
- Refine audience signals toward high-intent first-party data
- Deploy Customer Match lists (past purchasers) to force algorithm toward bottom-funnel users
- Implement stricter asset group exclusions

---

### Strategic tROAS Adjustments (Stepping Heuristic)

**Usage**: 78% of retail campaigns; 84% success rate when sufficient data provided.

| Objective | tROAS Action | Algorithmic Result |
|-----------|--------------|-------------------|
| Increase volume/market share | **Decrease** tROAS | Permission to buy slightly less efficient, higher-funnel traffic; expanded reach |
| Increase efficiency/margin | **Increase** tROAS | Bidding exclusively on highest-probability users; reduced volume, higher margin per order |

**Shock Heuristic (Mandatory)**:
- Maximum single adjustment: 10-15%
- Drastic changes (e.g., 250% → 400%) break algorithm confidence
- Result: Neural network discards weightings, severe learning phase, erratic CPCs, stalled spend
- **Protocol**: Gradual, iterative stepping over several days

---

### Cannibalization Defense (Critical)

**The Problem**: PMax aggressively cannibalizes standard Search campaigns, claiming false success, obscuring incrementality.

**Data Context**:
- 91.45% of accounts: PMax steals traffic from explicit exact match Search keywords
- 97.26% of accounts: Overlap on phrase/broad queries
- When &gt;10% performance difference exists: Search outperforms PMax in CVR 18.91% of time vs. PMax winning 6.17%

**Why Cannibalization Occurs**:
Search campaign temporarily ineligible due to:
- Budget exhaustion
- Narrow location targeting
- Restrictive ad schedules
  → PMax sweeps traffic by default

**Defense Protocol**:

1. **Search Term Extraction**
    - Use Search Term Insights in PMax
    - Identify converting queries
    - Cross-check against standard Search campaigns
    - **If absent**: Extract and add as Exact Match to Search campaign (force priority shift)

2. **Disable Auto-Apply**
    - Permanently disable "remove redundant keywords" recommendations
    - These keywords defend Search campaigns from PMax encroachment

3. **Account-Level Brand Exclusions**
    - PMax gravitates to branded terms to easily hit ROAS targets
    - **Mandatory**: Exclude brand terms to force PMax into top/mid-funnel prospecting
    - Objective: PMax discovers new customers, not harvests existing brand demand

### PMax Asset Group Strategy
- **Segmentation**: By product category, margin tier, or audience intent (mirror Custom Labels)
- **Audience Signals**: Prioritize first-party data (converters, high AOV customers, email lists)
- **Creative Assets**: Maximize variety for machine learning optimization; monitor asset performance reports

### When to Use PMax vs. Standard Shopping
| Scenario | Recommendation |
|----------|----------------|
| New account, limited data | Standard Shopping first (build conversion history) |
| Established account, 100+ monthly conversions | PMax for scale, maintain Search for brand defense |
| High-margin, hero products | Parallel Standard Shopping for direct control |
| Low-margin, catalog depth | PMax with strict tROAS and margin-based exclusions |
