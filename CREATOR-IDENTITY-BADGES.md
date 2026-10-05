# Nfinite Creators 0.58.1: Creator Identity Badges

Adds three distinct profile identity states:

- **Claimed**: the creator or authorized manager has taken ownership of a seeded profile.
- **PairOfDice Verified**: PairOfDice has manually verified identity or authorized representation.
- **PairOfDice Publisher**: an internal PairOfDice editorial, curation, or publishing channel.

## Admin controls

Creator Profile Builder → Profile & Ownership now includes:

- Account Class: Creator / PairOfDice Publisher
- Claim Status: Unclaimed / Claimed
- Verification Status: Unverified / PairOfDice Verified

Publisher status takes visual priority over Claimed/Verified. Publisher accounts are also rejected from the streaming-earnings eligibility layer so internal channels cannot accidentally receive creator streaming allocations.

Existing profiles default safely to Creator + Unclaimed + Unverified until explicitly changed.
