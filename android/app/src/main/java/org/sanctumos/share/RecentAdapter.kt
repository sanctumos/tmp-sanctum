// SPDX-License-Identifier: AGPL-3.0-or-later
package org.sanctumos.share

import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.view.LayoutInflater
import android.view.ViewGroup
import android.widget.Toast
import androidx.recyclerview.widget.RecyclerView
import org.sanctumos.share.databinding.ItemRecentBinding

class RecentAdapter(
    private val items: MutableList<ShareRecord>,
) : RecyclerView.Adapter<RecentAdapter.VH>() {

    class VH(val binding: ItemRecentBinding) : RecyclerView.ViewHolder(binding.root)

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH {
        val binding = ItemRecentBinding.inflate(LayoutInflater.from(parent.context), parent, false)
        return VH(binding)
    }

    override fun getItemCount(): Int = items.size

    override fun onBindViewHolder(holder: VH, position: Int) {
        val item = items[position]
        holder.binding.textFilename.text = item.filename
        holder.binding.textDirect.text = item.directUrl
        holder.binding.root.setOnClickListener {
            val cm = it.context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
            cm.setPrimaryClip(ClipData.newPlainText("direct_url", item.directUrl))
            Toast.makeText(it.context, R.string.copied, Toast.LENGTH_SHORT).show()
        }
        holder.binding.root.setOnLongClickListener {
            it.context.startActivity(
                Intent(Intent.ACTION_VIEW, Uri.parse(item.url.ifBlank { item.directUrl })),
            )
            true
        }
    }

    fun replaceAll(next: List<ShareRecord>) {
        items.clear()
        items.addAll(next)
        notifyDataSetChanged()
    }
}
