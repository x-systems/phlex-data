<?php

declare(strict_types=1);

namespace Phlex\Data\Model\Field\Type;

use Phlex\Data\Model;

/**
 * Stores valid email(s) as per configuration.
 *
 * Usage:
 *  $user->addField('email', ['Email']);
 *  $user->addField('email_mx_check', ['Email', 'dns_check'=>true]);
 *  *  $user->addField('email_with_name', ['Email', 'include_names'=>true]);
 *  $user->addField('emails', ['Email', 'allow_multiple'=>true, 'separator'=>[',',';']]);
 *
 * Various options can also be combined.
 */
class Email extends Model\Field\Type
{
    /**
     * @var bool Enable lookup for MX record for email addresses stored
     */
    public $dns_check = false;

    /**
     * @var bool Permit entry of multiple email addresses, separated with comma (and extra spaces)
     */
    public $allow_multiple = false;

    /**
     * @var bool Also allow entry of names in format "Romans <me@example.com>"
     */
    public $include_names = false;

    /**
     * @var array Array of allowed separators
     */
    public $separator = [','];

    /**
     * Validate and normalize value.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    protected function doNormalize($value)
    {
        // split value by any number of separator characters
        $emails = preg_split('/[' . implode('', array_map('preg_quote', $this->separator)) . ']+/', (string) $value, -1, \PREG_SPLIT_NO_EMPTY);

        if (!$this->allow_multiple && count($emails) > 1) {
            throw new ValidationException('Only a single email can be entered');
        }

        // now normalize each email
        $emails = array_map(function ($email) {
            $email = trim($email);

            if ($this->include_names) {
                $email = preg_replace('/^[^<]*<([^>]*)>/', '\1', $email);
            }

            // should actually run only domain trough idn_to_ascii(), but for validation purpose this way it's fine too
            $p = explode('@', $email);
            $user = $p[0] ?? null;
            $domain = $p[1] ?? null;

            if (!filter_var($user . '@' . $this->idn_to_ascii($domain), \FILTER_VALIDATE_EMAIL)) {
                throw new ValidationException('Email format is invalid');
            }

            if ($this->dns_check) {
                if (!checkdnsrr($this->idn_to_ascii($domain), 'MX')) {
                    throw new ValidationException('Email domain does not exist');
                }
            }

            return $email;
        }, $emails);

        return parent::doNormalize(implode(', ', $emails));
    }

    /**
     * Return translated address.
     *
     * @param string $domain
     *
     * @return string
     */
    protected function idn_to_ascii(?string $domain): ?string
    {
        return isset($domain) ? idn_to_ascii($domain, \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46) : null;
    }
}
