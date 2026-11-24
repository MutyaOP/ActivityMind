import os
import sys
import tempfile
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import config  # noqa: E402


class ConfigTest(unittest.TestCase):
    def write(self, text):
        f = tempfile.NamedTemporaryFile("w", suffix=".ini", delete=False, encoding="utf-8")
        f.write(text)
        f.close()
        self.addCleanup(os.unlink, f.name)
        return f.name

    def test_missing_file_has_helpful_error(self):
        with self.assertRaises(FileNotFoundError):
            config.load("/no/such/config.ini")

    def test_defaults_and_privacy_off_by_default(self):
        cfg = config.load(self.write("[server]\ntoken = amd_x\n"))
        self.assertEqual(cfg.token, "amd_x")
        self.assertFalse(cfg.track_window_titles)
        self.assertEqual(cfg.poll_interval_sec, 5)
        self.assertEqual(cfg.ignore_apps, [])

    def test_values_are_parsed(self):
        cfg = config.load(self.write(
            "[server]\nurl = http://site.test/\ntoken = amd_x\n"
            "[tracking]\npoll_interval_sec = 2\ntrack_window_titles = true\nignore_apps = KeePass, Bitwarden\n"
            "[sync]\ninterval_sec = 30\nbatch_size = 50\n"
        ))
        self.assertEqual(cfg.url, "http://site.test")            # слэш в конце убран
        self.assertEqual(cfg.poll_interval_sec, 2)
        self.assertTrue(cfg.track_window_titles)
        self.assertEqual(cfg.ignore_apps, ["keepass", "bitwarden"])
        self.assertEqual((cfg.sync_interval_sec, cfg.batch_size), (30, 50))


if __name__ == "__main__":
    unittest.main()
