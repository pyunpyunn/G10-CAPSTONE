package __PACKAGE__

import java.io.IOException
import java.net.InetAddress
import java.net.UnknownHostException
import java.net.URLEncoder
import java.net.URL
import javax.net.ssl.HttpsURLConnection
import okhttp3.Dns
import org.json.JSONObject

class NgrokDnsFallback : Dns {
    @Volatile
    private var cachedHostname: String? = null

    @Volatile
    private var cachedAddresses: List<InetAddress> = emptyList()

    @Volatile
    private var cacheExpiresAt = 0L

    override fun lookup(hostname: String): List<InetAddress> {
        val normalized = hostname.trimEnd('.').lowercase()
        if (!normalized.endsWith(".ngrok-free.dev") && !normalized.endsWith(".ngrok-free.app")) {
            return Dns.SYSTEM.lookup(hostname)
        }

        if (cachedHostname == normalized && cacheExpiresAt > System.currentTimeMillis() && cachedAddresses.isNotEmpty()) {
            return cachedAddresses
        }

        synchronized(this) {
            if (cachedHostname == normalized && cacheExpiresAt > System.currentTimeMillis() && cachedAddresses.isNotEmpty()) {
                return cachedAddresses
            }

            var lastFailure: Exception? = null
            for (resolver in RESOLVERS) {
                try {
                    val addresses = resolveWithHttps(resolver, normalized)
                    cachedHostname = normalized
                    cachedAddresses = addresses.first
                    cacheExpiresAt = System.currentTimeMillis() + addresses.second * 1000L
                    return cachedAddresses
                } catch (error: Exception) {
                    lastFailure = error
                }
            }

            try {
                return Dns.SYSTEM.lookup(hostname)
            } catch (systemFailure: UnknownHostException) {
                val failure = UnknownHostException("Unable to resolve the Ngrok API host using HTTPS DNS.")
                lastFailure?.let(failure::initCause)
                if (lastFailure != null) failure.addSuppressed(systemFailure)
                throw failure
            }
        }
    }

    private fun resolveWithHttps(resolver: String, hostname: String): Pair<List<InetAddress>, Long> {
        val query = URLEncoder.encode(hostname, Charsets.UTF_8.name())
        val connection = URL("$resolver?name=$query&type=A").openConnection() as HttpsURLConnection
        connection.connectTimeout = 4000
        connection.readTimeout = 4000
        connection.setRequestProperty("Accept", "application/dns-json")

        try {
            if (connection.responseCode != HttpsURLConnection.HTTP_OK) {
                throw IOException("HTTPS DNS resolver returned HTTP ${connection.responseCode}.")
            }

            val response = connection.inputStream.bufferedReader().use { it.readText() }
            val answers = JSONObject(response).optJSONArray("Answer")
                ?: throw IOException("HTTPS DNS resolver returned no address records.")
            val records = (0 until answers.length()).mapNotNull { index ->
                val record = answers.optJSONObject(index) ?: return@mapNotNull null
                val address = record.optString("data")
                if (record.optInt("type") != 1 || !IPV4_PATTERN.matches(address)) return@mapNotNull null
                InetAddress.getByName(address) to record.optLong("TTL", DEFAULT_TTL_SECONDS)
            }
            if (records.isEmpty()) throw IOException("HTTPS DNS resolver returned no IPv4 addresses.")

            val ttl = records.minOf { it.second }.coerceIn(MIN_TTL_SECONDS, MAX_TTL_SECONDS)
            return records.map { it.first }.distinct() to ttl
        } finally {
            connection.disconnect()
        }
    }

    private companion object {
        val RESOLVERS = listOf(
            "https://cloudflare-dns.com/dns-query",
            "https://dns.google/resolve",
        )
        val IPV4_PATTERN = Regex("""(?:\d{1,3}\.){3}\d{1,3}""")
        const val DEFAULT_TTL_SECONDS = 60L
        const val MIN_TTL_SECONDS = 30L
        const val MAX_TTL_SECONDS = 300L
    }
}
