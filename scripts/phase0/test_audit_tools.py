"""Small tests for audit correctness: SQL tuple boundaries and safe redaction."""

import unittest
from audit_database import insert_counts
from create_baseline import MARKER, redact


class AuditToolsTest(unittest.TestCase):
    def test_values_inside_strings_do_not_create_fake_rows(self):
        sql = "INSERT INTO `items` (`id`,`text`) VALUES (1,'comma, and (parentheses);'),(2,'it''s quoted');"
        self.assertEqual(insert_counts(sql), {'items': 2})

    def test_backslash_quote_and_multiple_inserts(self):
        sql = "INSERT INTO `a` (`x`) VALUES ('escaped\\\' ( , value');\nINSERT INTO `a` (`x`) VALUES ('next');\nINSERT INTO `b` (`x`) VALUES (1),(2);"
        self.assertEqual(insert_counts(sql), {'a': 2, 'b': 2})

    def test_credential_literals_are_redacted_but_code_keeps_line_numbers(self):
        original = "'password' => 'example-password-do-not-use',\n$config['LicenseKey'] = 'example-license';\n"
        cleaned = redact(original)
        self.assertNotIn('example-password-do-not-use', cleaned)
        self.assertNotIn('example-license', cleaned)
        self.assertEqual(len(cleaned.splitlines()), len(original.splitlines()))
        self.assertEqual(redact(cleaned), cleaned)

    def test_mysqli_and_define_literals_are_redacted(self):
        original = "$db = new mysqli('local', 'example-user', 'example-pass', 'example-db');\ndefine('GPWD', 'example-password');"
        cleaned = redact(original)
        self.assertNotIn('example-pass', cleaned)
        self.assertIn(MARKER, cleaned)
        self.assertEqual(redact(cleaned), cleaned)

    def test_password_input_and_hash_logic_is_not_rewritten(self):
        original = "$pass = md5($this->input->post('pass'));\n'data' => 'non-secret business text',\n'pass' => $pass;"
        self.assertEqual(redact(original), original)


if __name__ == '__main__':
    unittest.main()
