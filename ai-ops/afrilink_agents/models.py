"""Pydantic models for validated, structured agent outputs.

Design rules (see schemas.py): every field is required, no constraints, no Optionals.
Use sentinels ("" / [] / 0) for "unknown" rather than null, so the strict JSON schema
stays simple and the model always returns a complete object.
"""
from __future__ import annotations

from typing import Literal

from pydantic import BaseModel

Severity = Literal["critical", "high", "medium", "low", "info"]
Confidence = Literal["high", "medium", "low"]
Priority = Literal["P0", "P1", "P2", "P3"]


# --- MarketResearchAgent ------------------------------------------------------

class CountryOpportunity(BaseModel):
    country: str
    summary: str
    ecommerce_trends: list[str]
    payment_methods: list[str]
    buyer_behaviors: list[str]
    opportunities: list[str]
    risks: list[str]
    sources: list[str]


class MarketResearchOutput(BaseModel):
    region: str
    countries: list[CountryOpportunity]
    cross_cutting_insights: list[str]


# --- SecurityAuditAgent -------------------------------------------------------

class SecurityFinding(BaseModel):
    title: str
    severity: Severity
    category: str           # sql_injection | xss | auth | idor | secret | csrf | upload | config | dependency | other
    file: str
    line: int               # 0 = unknown
    description: str
    recommendation: str
    confidence: Confidence


class SecurityAuditOutput(BaseModel):
    summary: str
    findings: list[SecurityFinding]


# --- Models for the stub agents (so they are ready to wire up) -----------------

class CompetitorGap(BaseModel):
    competitor: str
    observation: str        # from PUBLIC information only
    gap_for_afrilink: str
    opportunity: str
    sources: list[str]


class CompetitorWatchOutput(BaseModel):
    summary: str
    gaps: list[CompetitorGap]


class RegulatoryChange(BaseModel):
    country: str
    area: str               # data_protection | e_commerce | payments | consumer | tax
    change: str
    impact_on_afrilink: str
    action_required: str
    sources: list[str]


class RegulatoryWatchOutput(BaseModel):
    summary: str
    changes: list[RegulatoryChange]


class GrowthIdea(BaseModel):
    market: str             # "Europe" | "Afrique de l'Ouest" | country
    segment: str
    channel: str            # community | partnership | content | paid | referral
    idea: str
    expected_effort: Literal["S", "M", "L"]
    sources: list[str]


class GrowthOutput(BaseModel):
    summary: str
    ideas: list[GrowthIdea]


class Recommendation(BaseModel):
    title: str
    rationale: str
    priority: Priority
    effort: Literal["S", "M", "L"]
    impacted_area: str      # auth | payments | catalog | i18n | infra | ux | security | ...
    references: list[str]   # ids of source propositions / messages


class SeniorDevOutput(BaseModel):
    summary: str
    recommendations: list[Recommendation]
