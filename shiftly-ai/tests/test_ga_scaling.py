import os
import sys
import unittest

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from app.schemas import GAParameters
from app.services.ga_engine import resolve_ga_parameters


class ResolveGaParametersTests(unittest.TestCase):
    def test_large_input_uses_smaller_search_budget(self) -> None:
        params = GAParameters(population_size=60, generations=100, elite_count=4, tournament_size=4, crossover_parent_one_rate=0.8, mutation_rate=0.08)

        scaled = resolve_ga_parameters(params, employee_count=500, days=7)

        self.assertLess(scaled.population_size, params.population_size)
        self.assertLess(scaled.generations, params.generations)
        self.assertGreaterEqual(scaled.elite_count, 1)

    def test_small_input_keeps_original_budget(self) -> None:
        params = GAParameters(population_size=40, generations=80, elite_count=2, tournament_size=4, crossover_parent_one_rate=0.8, mutation_rate=0.08)

        scaled = resolve_ga_parameters(params, employee_count=80, days=7)

        self.assertEqual(scaled.population_size, params.population_size)
        self.assertEqual(scaled.generations, params.generations)

    def test_very_large_input_scales_down_further(self) -> None:
        """Data sangat besar (>=800 pegawai) harus dapat budget LEBIH KECIL
        dari tier 400-799, bukan budget yang sama (lantai datar)."""
        params = GAParameters(population_size=60, generations=100, elite_count=4, tournament_size=4, crossover_parent_one_rate=0.8, mutation_rate=0.08)

        scaled_400 = resolve_ga_parameters(params, employee_count=500, days=14)
        scaled_800 = resolve_ga_parameters(params, employee_count=900, days=14)

        self.assertLessEqual(scaled_800.population_size, scaled_400.population_size)
        self.assertLessEqual(scaled_800.generations, scaled_400.generations)
        self.assertGreaterEqual(scaled_800.elite_count, 1)
        self.assertGreaterEqual(scaled_800.population_size, 4)  # tetap di atas batas minimum schema


if __name__ == "__main__":
    unittest.main()