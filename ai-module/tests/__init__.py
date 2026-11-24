import os
import sys

# Чтобы тесты находили helpers.py и analyzer.py и при `python -m unittest`, и при `discover -s tests`
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
