# Personal-data privacy contributor opt-out

## Decision

The search package does not register a Core privacy exporter and eraser, although its migrations create a user_agent_hash column.

## Reason

Search logs are pseudonymous (address and browser hashes, no account), not findable by email or account.
