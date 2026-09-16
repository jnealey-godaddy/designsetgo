---
name: chain-of-verification
description: Use when generating responses containing factual claims, API details, configuration specifics, version compatibility, or recalled knowledge that could be hallucinated - especially when not working directly from source code or command output
---

# Chain of Verification

## Overview

LLMs hallucinate. They generate confident answers that are completely wrong. Few-shot examples and extra context treat symptoms, not the disease.

**Core principle:** Separate generation from verification. Answer first, then fact-check independently, then revise. Models catch their own hallucinations when verification questions are answered independently from the original response.

## When to Use

**Use for ANY response involving:**
- Factual claims about APIs, libraries, or tools
- Configuration details, syntax, or parameter names
- Compatibility or version information
- WordPress hook names, function signatures, parameters
- Claims about what code does without having read it
- Technical recommendations based on recalled knowledge

**Use this ESPECIALLY when:**
- You're not 100% certain of specific details
- Answering from memory rather than from code you've read
- Making claims about behavior you haven't verified
- Providing version-specific information
- You catch yourself writing "I believe...", "I think...", or "IIRC..."

**Don't use when:**
- You've read the actual source code and are describing what you see
- Running commands and reporting their output
- Making subjective recommendations clearly labeled as opinions

## The Four Steps

### Step 1: Generate Baseline Response

Answer the question normally. Give your best answer without holding back.

### Step 2: Plan Verification Questions

Generate 3-5 questions that would expose errors in your baseline:
- "Is [specific claim] actually correct?"
- "Does [API/function] actually accept [parameter]?"
- "Is [version/compatibility claim] current?"
- "Am I confusing [X] with [Y]?"

**Key:** Questions must target specific, verifiable claims - not vague "is this right?"

### Step 3: Answer Independently

Answer each verification question **separately** from your baseline response. This prevents contamination - the tendency to defend your first answer instead of objectively checking it.

**Critical:** If you can't independently verify a claim, flag it as uncertain rather than confirming your baseline.

### Step 4: Generate Final Verified Response

Revise your baseline based on verification results:
- Correct any errors found
- Flag claims you couldn't verify
- Remove or qualify uncertain information

## Quick Reference

| Step | Action | Purpose |
|------|--------|---------|
| **1. Baseline** | Answer normally | Get initial response on paper |
| **2. Questions** | Generate 3-5 verification questions | Identify claims that could be wrong |
| **3. Verify** | Answer questions independently | Prevent confirmation bias |
| **4. Revise** | Correct based on verification | Deliver accurate response |

## Why This Works

**Traditional approach:** "Answer this question" - hope for accuracy

**Chain of Verification:** "Answer, verify, revise" - systematic fact-checking

The key insight: LLMs are good at verification when questions are asked independently. The problem is **contamination** - defending the first answer instead of objectively checking it. CoVe separates generation from verification.

## Common Mistakes

| Mistake | Fix | Why This Matters |
|---------|-----|------------------|
| Verification questions too vague | Target specific, verifiable claims | Vague questions get vague answers that don't catch errors |
| Answering verification in context of baseline | Answer each question fresh, independently | Contamination leads to confirming errors instead of catching them |
| Skipping verification for "obvious" answers | Obvious answers hallucinate too | LLMs are most confident when wrong - obviousness ≠ correctness |
| Only 1-2 verification questions | Use 3-5 to cover different claims | Each question targets one claim; more claims = more questions needed |
| Confirming baseline without genuine checking | If you can't verify independently, flag as uncertain | False confidence is worse than acknowledged uncertainty |

## Red Flags - Apply CoVe

- Stating API parameters from memory
- Claiming version compatibility without checking
- Describing function behavior you haven't read
- Confident statements about external tool behavior
- "I believe", "I think", "IIRC" in factual contexts

**All of these mean: Run verification before responding.**

## Integration with Other Skills

- **superpowers:verification-before-completion**: CoVe verifies factual accuracy of claims; that skill verifies task completion with command output evidence
- **superpowers:systematic-debugging**: Use CoVe when forming hypotheses about root causes based on recalled knowledge rather than traced evidence
