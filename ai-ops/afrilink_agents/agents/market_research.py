"""MarketResearchAgent (MVP, functional).

For each West-African country: research e-commerce trends, payment methods and buyer
behaviour (live web), then structure into opportunities for AfriLink. Weekly.

Flow per run: one web `gather` per country -> one `structure` call over all of them
-> a synthesis message + one proposition per opportunity (deduplicated).
"""
from __future__ import annotations

from ..base import BaseAgent
from ..budget import RunBudget
from ..models import MarketResearchOutput

GATHER_SYSTEM = (
    "Tu es analyste de marché e-commerce pour AfriLink, une marketplace qui relie "
    "l'Afrique de l'Ouest et l'Europe. Tu fais des recherches factuelles et récentes. "
    "Pour chaque demande, cherche sur le web des informations à jour (2025-2026) et "
    "cite systématiquement tes sources sous forme d'URLs. Concentre-toi sur : "
    "tendances e-commerce, modes de paiement réellement utilisés (mobile money, "
    "cartes, paiement à la livraison…), comportements d'achat, et opportunités "
    "concrètes pour une marketplace transfrontalière. Reste factuel, pas de spéculation."
)

STRUCTURE_SYSTEM = (
    "Tu convertis des analyses de marché en JSON structuré et fidèle. "
    "N'invente aucune donnée : n'utilise que ce qui figure dans le texte fourni. "
    "Conserve les URLs de sources. Réponds uniquement avec le JSON du schéma."
)


class MarketResearchAgent(BaseAgent):
    name = "market_research"
    frequency = "weekly"

    def execute(self, run_id: int, budget: RunBudget) -> str:
        countries = self.settings.market_countries
        analyses: list[tuple[str, str]] = []

        for country in countries:
            user = (
                f"Pays : {country}. Recherche pour 2025-2026 : (1) tendances e-commerce, "
                f"(2) modes de paiement dominants, (3) comportements d'achat, "
                f"(4) opportunités concrètes pour AfriLink (vente locale + import/export "
                f"vers l'Europe). Donne une synthèse structurée avec des sources (URLs)."
            )
            text = self.llm.gather(
                agent=self.name, run_id=run_id, budget=budget,
                system=GATHER_SYSTEM, user=user, model=self.model,
            )
            analyses.append((country, text))

        joined = "\n\n".join(f"### {country}\n{text}" for country, text in analyses)
        output: MarketResearchOutput = self.llm.structure(  # type: ignore[assignment]
            agent=self.name, run_id=run_id, budget=budget,
            system=STRUCTURE_SYSTEM,
            user=(
                "Région : Afrique de l'Ouest. À partir des analyses par pays ci-dessous, "
                "produis le JSON (un objet par pays + insights transversaux).\n\n" + joined
            ),
            schema_model=MarketResearchOutput, model=self.model,
            max_tokens=max(self.settings.structure_max_tokens, 8000),
        )

        self.db.insert_message(
            agent=self.name, kind="synthesis", topic="market_research",
            content=output.model_dump_json(), run_id=run_id,
        )

        proposed = 0
        for country_data in output.countries:
            for opportunity in country_data.opportunities:
                created = self.db.insert_proposition(
                    agent=self.name, ptype="market_opportunity",
                    title=f"[{country_data.country}] {opportunity[:120]}",
                    body=opportunity, priority="P2", country=country_data.country,
                    payload={
                        "summary": country_data.summary,
                        "payment_methods": country_data.payment_methods,
                        "buyer_behaviors": country_data.buyer_behaviors,
                        "sources": country_data.sources,
                    },
                    dedup_key=f"{country_data.country}:{opportunity[:80]}",
                    run_id=run_id,
                )
                if created is not None:
                    proposed += 1

        return f"{len(output.countries)} pays analysés, {proposed} opportunités proposées"
