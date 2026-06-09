"""CompetitorWatchAgent (STUB — to implement).

Role:      Analyse competitors from PUBLIC information only (features, UX, pricing,
           user reviews) and identify gaps in their offering = opportunities for us.
Frequency: daily.
Output:    one proposition per identified gap (ptype="competitor_gap").

⛔ HARD CONSTRAINT (must stay in the system prompt and the code): use only public
   information. NEVER scan, probe, fingerprint, or test the security of any third-party
   site. No port scans, no vuln scans, no auth testing, no crawling behind logins.
   This agent has web_search/web_fetch for reading public pages — nothing else.
"""
from __future__ import annotations

from ..base import BaseAgent
from ..budget import RunBudget

SYSTEM = (
    "Tu es analyste concurrentiel pour AfriLink. Tu n'utilises QUE des informations "
    "PUBLIQUES (pages publiques, tarifs affichés, avis utilisateurs, presse). "
    "INTERDICTION ABSOLUE de scanner, sonder, tester ou cartographier la sécurité d'un "
    "site tiers, de contourner une authentification ou d'accéder à des zones non "
    "publiques. Tu identifies les fonctionnalités, l'UX, les prix et les lacunes des "
    "concurrents, et tu en déduis des opportunités pour AfriLink. Cite tes sources."
)


class CompetitorWatchAgent(BaseAgent):
    name = "competitor_watch"
    frequency = "daily"
    implemented = False  # flip to True once execute() below is filled in

    def execute(self, run_id: int, budget: RunBudget) -> str:
        # Intended flow (mirrors MarketResearchAgent):
        #
        # competitors = ["Jumia", "Afrikrea/ANKA", "Glovo", ...]  # from config/env
        # analyses = []
        # for competitor in competitors:
        #     text = self.llm.gather(
        #         agent=self.name, run_id=run_id, budget=budget, system=SYSTEM,
        #         user=f"À partir d'informations publiques uniquement, analyse {competitor} : "
        #              f"fonctionnalités, UX, tarifs, avis utilisateurs, points faibles. "
        #              f"Déduis les lacunes = opportunités pour AfriLink. Cite les sources.",
        #         model=self.model,
        #     )
        #     analyses.append((competitor, text))
        #
        # output = self.llm.structure(
        #     agent=self.name, run_id=run_id, budget=budget, system=SYSTEM,
        #     user="Structure ces analyses:\n\n" + join(analyses),
        #     schema_model=CompetitorWatchOutput, model=self.model,
        # )
        #
        # for gap in output.gaps:
        #     self.db.insert_proposition(
        #         agent=self.name, ptype="competitor_gap",
        #         title=f"[{gap.competitor}] {gap.opportunity[:120]}",
        #         body=f"{gap.observation}\n\nLacune: {gap.gap_for_afrilink}\nOpportunité: {gap.opportunity}",
        #         priority="P2", payload={"sources": gap.sources},
        #         dedup_key=f"{gap.competitor}:{gap.opportunity[:80]}", run_id=run_id,
        #     )
        # return f"{len(output.gaps)} lacunes identifiées"
        raise NotImplementedError("CompetitorWatchAgent: implémenter execute() puis implemented=True")
