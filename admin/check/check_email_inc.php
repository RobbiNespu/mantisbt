<?php
# MantisBT - A PHP based bugtracking system

# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains configuration checks for email issues
 *
 * @package MantisBT
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses check_api.php
 * @uses config_api.php
 * @uses utility_api.php
 */

if( !defined( 'CHECK_EMAIL_INC_ALLOW' ) ) {
	return;
}

# MantisBT Check API
require_once( 'check_api.php' );
require_api( 'config_api.php' );
require_api( 'utility_api.php' );
require_api( 'database_api.php' );

check_print_section_header_row( 'Email' );

$t_email_options = array(
	'webmaster_email',
	'from_email',
	'return_path_email'
);

foreach( $t_email_options as $t_email_option ) {
	$t_email = config_get_global( $t_email_option );
	check_print_test_row(
		$t_email_option . ' configuration option has a valid email address specified',
		!preg_match( '/@example\.com$/', $t_email ),
		array( false => 'You need to specify a valid email address for the ' . $t_email_option . ' configuration option.' )
	);
}

check_print_test_warn_row(
	'Email addresses are validated',
	config_get_global( 'validate_email' ),
	array( false => 'You have disabled email validation checks. For security reasons it is suggested that you enable these validation checks.' )
);

check_print_test_row(
	'send_reset_password = ON requires allow_blank_email = OFF',
	!config_get_global( 'send_reset_password' ) || !config_get_global( 'allow_blank_email' )
);

check_print_test_row(
	'send_reset_password = ON requires enable_email_notification = ON',
	!config_get_global( 'send_reset_password' ) || config_get_global( 'enable_email_notification' )
);

check_print_test_row(
	'allow_signup = ON requires enable_email_notification = ON',
	!config_get_global( 'allow_signup' ) || config_get_global( 'enable_email_notification' )
);

check_print_test_row(
	'allow_signup = ON requires send_reset_password = ON',
	!config_get_global( 'allow_signup' ) || config_get_global( 'send_reset_password' )
);

# Check for duplicate email addresses (case insensitive)
# Using a sub-query within the IN clause's SELECT statement, as a workaround for
# MySQL >= 5.7 with sql_mode=only_full_group_by throwing ERROR 1055 (42000):
# Expression #1 of HAVING clause is not in GROUP BY clause
$t_user_table = db_get_table( 'user' );
$t_sql = <<< ENDSQL
SELECT lower(email) email, username
FROM {$t_user_table} 
WHERE lower(email) IN (
	SELECT email 
	FROM (
		SELECT lower(email) email, COUNT(*)
		FROM {$t_user_table}
		GROUP BY lower(email) HAVING COUNT(*) > 1
		) tmp
	)
ORDER BY lower(email), username
ENDSQL;
$t_query = new DbQuery( $t_sql );
$t_rows = $t_query->fetch_all();

$t_duplicate_emails = array();
if( $t_rows ) {
	foreach( $t_rows as $t_row ) {
		/**
		 * @var string $v_email
		 * @var string $v_username
		 */
		extract( $t_row, EXTR_PREFIX_ALL, 'v' );
		$t_duplicate_emails[$v_email][] = $v_username;
	}
	foreach( $t_duplicate_emails as $t_email => &$t_usernames ) {
		$t_usernames = "$t_email (" . implode(', ', $t_usernames ) . ")";
	}
}

// Fail check if emails should be unique, just issue a warning otherwise
$t_function = config_get_global( 'email_ensure_unique' )
	? 'check_print_test_row'
	: 'check_print_test_warn_row';
$t_function(
	'There are no duplicate email addresses, regardless of case',
	count( $t_duplicate_emails ) == 0,
	'Duplicates found: ' . implode('; ', $t_duplicate_emails )
);
