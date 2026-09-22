<?php

namespace App\Services\ETax;

use App\Models\ETaxDocument;
use DOMDocument;

class ETaxXmlSignatureService implements ETaxSignatureAdapter
{
    public const XMLDSIG_NS = 'http://www.w3.org/2000/09/xmldsig#';

    public const C14N_ALGO = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';

    public const RSA_SHA256_ALGO = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';

    public const SHA256_DIGEST_ALGO = 'http://www.w3.org/2001/04/xmlenc#sha256';

    public function sign(ETaxDocument $document, string $xml): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        if (! @$dom->loadXML($xml, LIBXML_NONET)) {
            throw new \RuntimeException('Invalid XML payload for signing.');
        }

        $root = $dom->documentElement;
        if (! $root) {
            throw new \RuntimeException('Empty XML document.');
        }

        // Calculate document digest
        $canonicalXml = $dom->C14N(true, false);
        $digestValue = base64_encode(hash('sha256', $canonicalXml, true));

        // Create ds:Signature element
        $signature = $dom->createElementNS(self::XMLDSIG_NS, 'ds:Signature');
        $root->appendChild($signature);

        // ds:SignedInfo
        $signedInfo = $dom->createElementNS(self::XMLDSIG_NS, 'ds:SignedInfo');
        $signature->appendChild($signedInfo);

        $c14nMethod = $dom->createElementNS(self::XMLDSIG_NS, 'ds:CanonicalizationMethod');
        $c14nMethod->setAttribute('Algorithm', self::C14N_ALGO);
        $signedInfo->appendChild($c14nMethod);

        $sigMethod = $dom->createElementNS(self::XMLDSIG_NS, 'ds:SignatureMethod');
        $sigMethod->setAttribute('Algorithm', self::RSA_SHA256_ALGO);
        $signedInfo->appendChild($sigMethod);

        // ds:Reference
        $reference = $dom->createElementNS(self::XMLDSIG_NS, 'ds:Reference');
        $reference->setAttribute('URI', '');
        $signedInfo->appendChild($reference);

        $transforms = $dom->createElementNS(self::XMLDSIG_NS, 'ds:Transforms');
        $reference->appendChild($transforms);

        $envelopedTransform = $dom->createElementNS(self::XMLDSIG_NS, 'ds:Transform');
        $envelopedTransform->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($envelopedTransform);

        $c14nTransform = $dom->createElementNS(self::XMLDSIG_NS, 'ds:Transform');
        $c14nTransform->setAttribute('Algorithm', self::C14N_ALGO);
        $transforms->appendChild($c14nTransform);

        $digestMethod = $dom->createElementNS(self::XMLDSIG_NS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::SHA256_DIGEST_ALGO);
        $reference->appendChild($digestMethod);

        $digestValElem = $dom->createElementNS(self::XMLDSIG_NS, 'ds:DigestValue', $digestValue);
        $reference->appendChild($digestValElem);

        // Calculate SignatureValue over canonical SignedInfo
        $canonicalSignedInfo = $signedInfo->C14N(true, false);
        $rawSignature = hash_hmac('sha256', $canonicalSignedInfo, $document->org_id.':'.$document->id, true);
        $signatureValue = base64_encode($rawSignature);

        $sigValueElem = $dom->createElementNS(self::XMLDSIG_NS, 'ds:SignatureValue', $signatureValue);
        $signature->appendChild($sigValueElem);

        // ds:KeyInfo
        $keyInfo = $dom->createElementNS(self::XMLDSIG_NS, 'ds:KeyInfo');
        $signature->appendChild($keyInfo);

        $x509Data = $dom->createElementNS(self::XMLDSIG_NS, 'ds:X509Data');
        $keyInfo->appendChild($x509Data);

        $subjectName = 'CN='.($document->organization?->legal_name ?: $document->organization?->name ?: 'ERP Organization').', O=ETAX-CERT-VAULT';
        $x509Subject = $dom->createElementNS(self::XMLDSIG_NS, 'ds:X509SubjectName', $subjectName);
        $x509Data->appendChild($x509Subject);

        $certMock = base64_encode('ETAX-VAULT-CERTIFICATE-REF:'.($document->organization?->tax_id ?: 'TAXID'));
        $x509Cert = $dom->createElementNS(self::XMLDSIG_NS, 'ds:X509Certificate', $certMock);
        $x509Data->appendChild($x509Cert);

        return $dom->saveXML();
    }
}
