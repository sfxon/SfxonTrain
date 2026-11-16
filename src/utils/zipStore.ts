const CRC_TABLE = (() => {
    const table = new Uint32Array(256)
    for (let n = 0; n < 256; n++) {
        let c = n
        for (let k = 0; k < 8; k++) {
            c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1
        }
        table[n] = c >>> 0
    }
    return table
})()

function crc32(bytes: Uint8Array): number {
    let crc = 0xffffffff
    for (let i = 0; i < bytes.length; i++) {
        crc = CRC_TABLE[(crc ^ bytes[i]) & 0xff] ^ (crc >>> 8)
    }
    return (crc ^ 0xffffffff) >>> 0
}

function toDosDateTime(date: Date): { time: number, date: number } {
    const year = Math.max(date.getFullYear(), 1980)

    return {
        time: (date.getHours() << 11) | (date.getMinutes() << 5) | (date.getSeconds() >> 1),
        date: ((year - 1980) << 9) | ((date.getMonth() + 1) << 5) | date.getDate(),
    }
}

const UTF8_FLAG = 0x0800

export class ZipStoreWriter {
    private readonly parts: BlobPart[] = []
    private readonly centralDirectory: Uint8Array[] = []
    private offset = 0

    async addFile(name: string, data: Blob, modified: Date = new Date()): Promise<void> {
        if (this.centralDirectory.length >= 0xffff) {
            throw new Error('ZIP: zu viele Einträge (ZIP64 wird nicht unterstützt).')
        }

        const nameBytes = new TextEncoder().encode(name)
        const size = data.size
        const crc = crc32(new Uint8Array(await data.arrayBuffer()))
        const dos = toDosDateTime(modified)

        // Local file header (30 Bytes) + Dateiname
        const local = new DataView(new ArrayBuffer(30))
        local.setUint32(0, 0x04034b50, true)
        local.setUint16(4, 20, true) // version needed
        local.setUint16(6, UTF8_FLAG, true)
        local.setUint16(8, 0, true) // method: store
        local.setUint16(10, dos.time, true)
        local.setUint16(12, dos.date, true)
        local.setUint32(14, crc, true)
        local.setUint32(18, size, true) // compressed size
        local.setUint32(22, size, true) // uncompressed size
        local.setUint16(26, nameBytes.length, true)
        local.setUint16(28, 0, true) // extra length

        // Central directory header (46 Bytes) + Dateiname
        const central = new Uint8Array(46 + nameBytes.length)
        const centralView = new DataView(central.buffer)
        centralView.setUint32(0, 0x02014b50, true)
        centralView.setUint16(4, 20, true) // version made by
        centralView.setUint16(6, 20, true) // version needed
        centralView.setUint16(8, UTF8_FLAG, true)
        centralView.setUint16(10, 0, true) // method: store
        centralView.setUint16(12, dos.time, true)
        centralView.setUint16(14, dos.date, true)
        centralView.setUint32(16, crc, true)
        centralView.setUint32(20, size, true)
        centralView.setUint32(24, size, true)
        centralView.setUint16(28, nameBytes.length, true)
        centralView.setUint16(30, 0, true) // extra length
        centralView.setUint16(32, 0, true) // comment length
        centralView.setUint16(34, 0, true) // disk number
        centralView.setUint16(36, 0, true) // internal attributes
        centralView.setUint32(38, 0, true) // external attributes
        centralView.setUint32(42, this.offset, true) // offset of local header
        central.set(nameBytes, 46)

        this.parts.push(local.buffer, nameBytes, data)
        this.centralDirectory.push(central)
        this.offset += 30 + nameBytes.length + size

        if (this.offset > 0xffffffff) {
            throw new Error('ZIP: Archiv zu groß (ZIP64 wird nicht unterstützt).')
        }
    }

    finish(): Blob {
        const centralSize = this.centralDirectory.reduce((sum, entry) => sum + entry.length, 0)

        const end = new DataView(new ArrayBuffer(22))
        end.setUint32(0, 0x06054b50, true)
        end.setUint16(4, 0, true) // this disk
        end.setUint16(6, 0, true) // disk with central directory
        end.setUint16(8, this.centralDirectory.length, true)
        end.setUint16(10, this.centralDirectory.length, true)
        end.setUint32(12, centralSize, true)
        end.setUint32(16, this.offset, true)
        end.setUint16(20, 0, true) // comment length

        return new Blob([...this.parts, ...this.centralDirectory, end.buffer], { type: 'application/zip' })
    }
}