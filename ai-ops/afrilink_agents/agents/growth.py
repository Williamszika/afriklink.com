"""GrowthAgent (STUB — to implement).

Role:      Identify channels and segments to acquire users in Europe and Africa
           (campaign ideas, communities, partnerships).
Frequency: weekly.
Output:    one proposition per growth idea (ptype="growth_idea").
"""
from __future__ import annotations

from ..base import BaseAgent
from ..budget import RunBudget

SYSTEM = (
    "Tu es responsable acquisition pour AfriLink, marketplace qui relie l'Afrique de "
    "l'Ouest et l'Europe (diaspora, commerçants, prestataires). Tu proposes des canaux "
    "et segments concrets pour acquérir des utilisateurs des deux côtés : communautés, "
    "partenariats, contenu, référral, acquisition payante. Pour chaque idée : marché, "
    "segment, canal, description, effort estimé, sources/exemples. Reste actionnable."
)


class GrowthAgent(BaseAgent):
    name = "growth"
    frequency = "weekly"
    implemented = False

    def execute(self, run_id: int, budget: RunBudget) -> str:
        # Intended flow:
        #   text = self.llm.gather(..., user="Idées d'acquisition pour AfriLink en Europe "
        #          "(diaspora ouest-africaine) et en Afrique de l'Ouest : communautés, "
        #          "partenariats, canaux de contenu, exemples concrets. Cite des sources.")
        #   output = self.llm.structure(..., schema_model=GrowthOutput)
        #   for idea in output.ideas:
        #       self.db.insert_proposition(
        #           agent=self.name, ptype="growth_idea",
        #           title=f"[{idea.market}/{idea.channel}] {idea.idea[:110]}",
        #           body=idea.idea, priority="P2",
        #           payload={"segment": idea.segment, "effort": idea.expected_effort,
        #                    "sources": idea.sources},
        #           dedup_key=f"{idea.market}:{idea.idea[:80]}", run_id=run_id)
        #   return f"{len(output.ideas)} idées de croissance"
        raise NotImplementedError("GrowthAgent: implémenter execute() puis implemented=True")
