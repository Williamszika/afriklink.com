"""SeniorDevAgent (STUB — to implement).

Role:      Read the other agents' propositions (market, competitor, security…) and
           produce concrete, prioritized technical recommendations to improve the site.
Frequency: event — triggered by the orchestrator when new propositions arrive
           (capped by MAX_AGENT_RELAUNCHES to avoid loops).
Output:    one proposition per recommendation (ptype="recommendation"), with
           source_refs linking back to the propositions it synthesised.
Model fit: critical reasoning → keep on the default Opus model. No web needed
           (it reasons over the DB), so it uses structure() directly.
"""
from __future__ import annotations

from ..base import BaseAgent
from ..budget import RunBudget

SYSTEM = (
    "Tu es un ingénieur senior / architecte pour AfriLink (PHP 8.4 + MySQL, sécurité "
    "intégrée par défaut). On te fournit des propositions issues d'autres agents "
    "(opportunités marché, lacunes concurrentes, findings de sécurité). Tu produis des "
    "recommandations techniques concrètes, priorisées (P0–P3) et chiffrées en effort "
    "(S/M/L), pour améliorer la plateforme. Référence les ids des propositions sources. "
    "Sécurité d'abord ; reste réaliste pour un solo builder. JSON du schéma uniquement."
)


class SeniorDevAgent(BaseAgent):
    name = "senior_dev"
    frequency = "event"
    implemented = False

    def execute(self, run_id: int, budget: RunBudget) -> str:
        # Intended flow:
        #
        # pending = self.db.fetch_propositions(status="pending", limit=60)
        # # exclude our own recommendations to avoid feedback loops
        # inputs = [p for p in pending if p["agent"] != self.name]
        # if not inputs:
        #     return "aucune nouvelle proposition à synthétiser"
        #
        # context = "\n".join(
        #     f"- #{p['id']} [{p['agent']}/{p['ptype']}] {p['title']} :: {p['body'][:300]}"
        #     for p in inputs
        # )
        # output = self.llm.structure(
        #     agent=self.name, run_id=run_id, budget=budget, system=SYSTEM,
        #     user="Propositions à synthétiser:\n" + context +
        #          "\n\nProduis des recommandations techniques priorisées (réfère les #ids).",
        #     schema_model=SeniorDevOutput, model=self.model,
        #     max_tokens=max(self.settings.structure_max_tokens, 8000),
        # )
        # for rec in output.recommendations:
        #     self.db.insert_proposition(
        #         agent=self.name, ptype="recommendation", title=rec.title,
        #         body=f"{rec.rationale}\n\nZone: {rec.impacted_area} | Effort: {rec.effort}",
        #         priority=rec.priority, category=rec.impacted_area,
        #         source_refs=rec.references, dedup_key=rec.title[:90], run_id=run_id,
        #     )
        # return f"{len(output.recommendations)} recommandations"
        raise NotImplementedError("SeniorDevAgent: implémenter execute() puis implemented=True")
