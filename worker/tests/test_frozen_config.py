import os
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch
from amo_worker.config import load_worker_env

class FrozenConfigTest(unittest.TestCase):
    def test_config_is_next_to_executable_not_extraction_folder(self):
        with tempfile.TemporaryDirectory() as folder:
            config=Path(folder)/'.env.worker'
            config.write_text('AMO_NODE_NAME="frozen-worker"\n',encoding='utf-8')
            config.chmod(0o600)
            with patch.object(sys,'frozen',True,create=True),\
                 patch.object(sys,'executable',str(Path(folder)/'aadi-worker.exe')), \
                 patch.dict(os.environ,{},clear=False):
                os.environ.pop('AMO_NODE_NAME',None)
                try:
                    load_worker_env()
                    self.assertEqual('frozen-worker',os.environ['AMO_NODE_NAME'])
                finally:
                    os.environ.pop('AMO_NODE_NAME',None)
