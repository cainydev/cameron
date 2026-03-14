# Module: Optimization & Scaling Protocols
## Fetch Condition: Scaling decisions, bid adjustments, budget reallocation, or performance optimization

### Scaling Winners Safely

**Pre-Scaling Diagnosis**: Determine constraint type before action.

#### Budget-Constrained Campaigns
**Indicators**:
- 90%+ Search Impression Share
- Frequently exhausting daily budget before day-end
- High ROAS, limited by capital

**Scaling Protocol**:
- Increase daily budget 15-20% every 3-5 days
- **Rationale**: Algorithm already winning majority of entered auctions; additional budget extends active hours without altering CPA dynamics
- Monitor for efficiency degradation; pause escalation if ROAS drops &gt;10%

#### Bid-Constrained Campaigns
**Indicators**:
- Unrestricted budget (not exhausting)
- Losing impression share due to Ad Rank (low bids)
- High ROAS, limited by efficiency targets

**Scaling Protocol**:
- Systematically relax tROAS constraint (e.g., 300% → 285%)
- OR remove maximum CPC limits on portfolio bid strategy
- **Rationale**: Algorithmic permission to enter more expensive, competitive, high-converting auctions
- Monitor incremental volume vs. efficiency trade-off

---

### Portfolio Restructuring Plans

**Context**: Product evaluation identifies multiple Zombies and Hidden Gems simultaneously.

**Fragmented Approach (Forbidden)**: 19 individual micro-tasks for 15 Zombies + 4 Gems

**Cohesive Plan Required**:

**Phase 1: Capital Extraction (Days 1-2)**
- Identify total budget consumed by Zombie products (30-60 day lookback)
- Route all Zombies to "Low Priority" campaign via Custom Label updates
- Apply defensive tROAS (account target +50%) or -50% Max CPC reduction

**Phase 2: Liquidity Reallocation (Days 2-3)**
- Calculate extracted capital from Zombie suppression
- Reallocate 60% to Hidden Gems (exploration campaigns, -20% tROAS)
- Reallocate 40% to established Winners (budget increase or bid relaxation)

**Phase 3: Validation (Days 7-14)**
- Monitor Hidden Gem conversion velocity toward 30-conversion threshold
- Evaluate efficiency impact on Winner campaigns
- Adjust allocation ratios based on early performance signals

---

### Brand Protection & Search Integrity

**Structural Requirements**:
- Brand campaigns isolated with dedicated budget
- Non-brand campaigns strictly excluded from brand terms (negative keywords)
- PMax brand exclusions at account level (see Module 5)

**Brand Campaign Defense**:
- Maintain 95%+ Search Impression Share for core brand terms
- Defend against competitor conquesting (monitor Auction Insights)
- Separate match types: Exact Match priority, Phrase/Broad with negatives

---

### Seasonal & Event-Based Optimization

**Pre-Season Protocol (4 weeks before)**:
- Identify seasonal velocity products via Custom Labels
- Gradually relax tROAS 10-15% to build algorithmic momentum
- Increase budgets 20-30% in anticipation of demand spike
- Expand audience signals to include previous seasonal purchasers

**Peak Season Protocol**:
- Monitor inventory levels; pause if stock-out risk
- Aggressive bid strategies for high-margin seasonal items
- Real-time Search Terms monitoring for emerging query trends

**Post-Season Protocol**:
- Immediate tROAS tightening to pre-season levels
- Budget reduction to maintenance levels
- Analyze cohort performance for next-year planning

---

### Continuous Optimization Loops

**Daily**:
- Spend pacing vs. budget caps
- Conversion tracking alerts
- Sudden drop detection (Stage 1)

**Weekly**:
- Search Terms report analysis (n-gram extraction)
- Zombie product identification
- Hidden Gem detection via GA4 assisted conversions

**Monthly**:
- Custom Label recalibration (performance clusters, margin tiers)
- Campaign consolidation/separation decisions (100-Conversion Rule)
- MER analysis vs. platform-reported ROAS

**Quarterly**:
- Account architecture audit (structural pollution assessment)
- PMax cannibalization analysis
- Competitive landscape review (Auction Insights trends)

---

### Emergency Brake Protocols

| Condition | Immediate Action | Recovery Path |
|-----------|-----------------|---------------|
| Tracking failure | Pause all affected campaigns | Resume post-fix + 24-hour validation |
| 404 on hero product | Pause product group/campaign | Resume post-fix + test conversion |
| &gt;50% daily budget spent on single non-converting term | Emergency negative keyword | Add to account-level exclusion list |
| PMax &gt;50% Display/Video with no assisted conversions | Audience signal reset + brand exclusion audit | 7-day monitoring period |
| Competitor brand term conquesting (your brand) | Increase brand campaign bids 25% | Evaluate legal/trademark options |
