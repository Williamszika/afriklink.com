"""RegulatoryWatchAgent (STUB — to implement).

Role:      Watch regulatory changes (data protection / privacy, e-commerce, payments,
           consumer, tax) across the covered countries, and assess impact on AfriLink.
Frequency: weekly.
Output:    one proposition per detected change (ptype="regulatory_change").
Model fit: legal nuance → keep on the default Opus model.
"""
from __future__ import annotations

from ..base import BaseAgent
from ..budget import RunBudget

SYSTEM = (
    "Tu es analyste en conformité pour AfriLink (marketplace Afrique de l'Ouest ↔ Europe). "
    "Tu surveilles les évolutions réglementaires récentes : protection des données / vie "
    "privée (RGPD côté UE et lois locales), e-commerce, paiements, droit de la "
    "consommation, fiscalité (TVA/IOSS, DAC7…). Pour chaque changement : pays, domaine, "
    "nature du changement, impact concret sur AfriLink, action requise, sources. "
    "Tu fournis des repères, pas un avis juridique. Cite tes sources (URLs officielles)."
)


class RegulatoryWatchAgent(BaseAgent):
    name = "regulatory_watch"
    frequency = "weekly"
    implemented = False

    def execute(self, run_id: int, budget: RunBudget) -> str:
        # Intended flow:
        #   for area in ["protection des données", "e-commerce", "paiements", "fiscalité"]:
        #       text = self.llm.gather(..., user=f"Changements réglementaires récents "
        #              f"({area}) en UE et dans: {', '.join(self.settings.market_countries)}. "
        #              f"Impact pour une marketplace transfrontalière. Sources officielles.")
        #   output = self.llm.structure(..., schema_model=RegulatoryWatchOutput)
        #   for change in output.changes:
        #       self.db.insert_proposition(
        #           agent=self.name, ptype="regulatory_change",
        #           title=f"[{change.country}/{change.area}] {change.change[:100]}",
        #           body=f"Impact: {change.impact_on_afrilink}\nAction: {change.action_required}",
        #           priority="P1", country=change.country, payload={"sources": change.sources},
        #           dedup_key=f"{change.country}:{change.change[:80]}", run_id=run_id)
        #   return f"{len(output.changes)} changements détectés"
        raise NotImplementedError("RegulatoryWatchAgent: implémenter execute() puis implemented=True")
