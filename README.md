# Personal Library on XigmaNAS – Documentation

A self-hosted personal book library running on a home NAS, reachable from any device on the local network (including e-ink Kindle browsers).

## 1. Overview

This project turns an old laptop into a small home server (NAS) and runs a lightweight PHP website on it. The site lists the e-books stored on the NAS and lets any device on the local network browse, search and download them.

**What was built**

- A NAS server running XigmaNAS on a Dell Latitude E4310 laptop.
- Two storage pools on the server (root pool and a larger data pool).
- A single-file PHP website (`index.php`) that works as a personal library.
- Remote administration over SSH.
- A static IP address so the library is always reachable at the same address.

&#91;embedded content: setup overview · 2 clients, 1 NAS, 4 services\]

The browser and the laptop reach the NAS over the local network; the library site, the iSCSI drive and the VM all run on the same server.

## 2. Server Setup

### 2.1 Hardware

| Item | Details |
| --- | --- |
| Machine | Dell Latitude E4310 (laptop used as server) |
| Storage | 500 GB HDD |
| Memory (RAM) | 2 × 4 GB DDR3 (8 GB total) |
| Operating system | XigmaNAS |

### 2.2 Storage pools

| Pool | Size | Swap usage |
| --- | --- | --- |
| Root pool | 53.15 GB | 2.14 GB |
| Data pool | 442.38 GB | 2.14 GB |

The smaller pool holds the operating system. The larger pool is the main storage area, where the books are kept.

#### Swap usage explained

Swap is a reserved area on disk that the system uses as overflow memory when RAM is full. The server has 8 GB of RAM, and the dashboard shows 2.14 GB of swap in use, so at some point the system moved less-used data from RAM to the hard disk to free memory for active tasks.

- **Why it matters:** disk is much slower than RAM, and this server uses a 500 GB HDD, so heavy swapping can make the web interface and file transfers feel slow.
- **Is 2.14 GB a problem?** Not by itself. Swap holding idle data is normal. It is only a concern if the number keeps growing or the server becomes sluggish.
- **Same value on both pools:** swap belongs to the whole system, not to a single pool, so the same figure appears next to both pools.
- **If it grows:** close unused services, or add more RAM if the laptop supports it.

### 2.3 Network

- The server was given a **static IP address**, so the address does not change after a reboot or router restart.
- Because the IP is fixed, the library can be opened by typing the same address in any browser on the local network (phone, PC, Kindle).

### 2.4 Remote access (SSH)

SSH is used to connect to the server for administration, so there is no need for a screen or keyboard attached to the laptop.

```
ssh <username>@<server-static-ip>
```

### 2.5 Virtual machine (in progress)

A virtual machine running **Ubuntu Server** is currently being set up on the XigmaNAS server.

- **Networking:** the VM uses a **bridged** network connection, so it appears on the local network as its own device and gets its own IP address, separate from the NAS.
- **Purpose:** it lets extra services run in a separate Ubuntu environment without changing the NAS itself.
- **Resources:** the VM shares the server's 8 GB of RAM and the HDD with XigmaNAS, so keep an eye on swap usage while it runs.
- **Tip:** give the VM a static IP or a DHCP reservation in the router, as was done for the NAS, so its address does not change.

&#91;embedded content: bridged networking · host and VM\]

With a bridge, the VM joins the local network as a second device on the same server, so the NAS and the VM can each be reached by their own IP.

### 2.6 iSCSI storage and laptop backups

Besides file sharing, the NAS also provides storage over **iSCSI**. iSCSI lets the NAS present part of its hard drive to another computer as if it were a local disk attached to that computer.

- **How it is used:** the NAS hard drive is shared over iSCSI to the Windows laptop, where it appears as a normal drive named **ISCSI XIGMANAS (D:)**.
- **Capacity:** the drive shows 379 GB in total, with 354 GB free.
- **Purpose:** the laptop uses this drive to store backups, so important files are kept on the NAS and not only on the laptop.
- **Network:** the connection runs over the local network, so backups are as fast as the network and the server's HDD allow.

&#91;embedded content: pool sizes typed in chat, drive D: from a Windows screenshot · 3 volumes\]

About 25 GB of the iSCSI drive is used so far.

## 3. The Library Website

### 3.1 Summary

A small website written in PHP as a **single file** (`index.php`). It runs on the NAS web server and is only available on the local network. It scans a books folder, builds an index, and shows the books as a searchable, paginated list.

### 3.2 Design goals

- Works on e-ink browsers (Kindle): no JavaScript, no animations, high contrast, large tap targets.
- Paginated lists (15 books per page) to keep screen refreshes to a minimum.
- Everything in one file, with no database and no external libraries.

### 3.3 Features

- **Settings page** to choose the folder where the books are stored on the NAS.
- **Automatic scan** of the folder and all its subfolders; hidden files and folders are skipped.
- **Search** by title or folder name (multiple words are all required to match).
- **Filter by format**, with a count for each format (for example EPUB 120, PDF 45).
- **Sorting**: A-Z, Newest, or Largest.
- **Pagination** with Prev / Next buttons and a page counter.
- **Rescan** button to refresh the list after adding books.
- **Download** of any book with the correct file type and file name.

### 3.4 Supported formats

EPUB, PDF, MOBI, AZW, AZW3, AZW4, KFX, KF8, PRC, TXT, RTF, DOC, DOCX, ODT, FB2, DJVU, CBZ, CBR, LIT, CHM, HTM, HTML.

### 3.5 How it works

1. On first use, the user opens the **Settings** page and enters the path of the books folder (for example `/mnt/data/books`).
2. The script checks that the folder exists and is readable, then saves the path to `data/config.json`.
3. The folder is scanned and the result (path, size, modified date, extension) is stored in `data/index.json`.
4. The home page reads the index, applies search, format filter and sorting, and shows one page of results.
5. Tapping a book calls the download action, which streams the file to the device.

&#91;embedded content: request flow · 4 parts\]

Searching and sorting read the saved list; the books folder is only opened for a download or a rescan.

### 3.6 Files

| File | Purpose |
| --- | --- |
| `index.php` | The whole application |
| `data/config.json` | Saved settings (books folder path) |
| `data/index.json` | Cached list of books from the last scan |

The `data` folder is created automatically and must be writable by the web server.

### 3.7 Security notes

- Downloads are limited to files inside the configured books folder (the path is resolved and checked, which blocks `../` tricks).
- Only the listed book extensions can be downloaded.
- The site has no login, so it should only be used on the trusted local network and not exposed to the internet.

## 4. Usage

1. Open `http://<server-static-ip>/<path-to>/index.php` in any browser on the local network.
2. Use **Search**, the **format chips** or **Sort** to find a book.
3. Tap the book title to download it.
4. After copying new books to the NAS, tap **Rescan**.

## 5. Maintenance

- **Add books:** copy files into the books folder (or subfolders), then press Rescan.
- **Change the books folder:** Settings, enter the new path, Save and scan.
- **If saving fails:** make sure the `data` folder is writable by the web server.
- **If the library is empty:** check that the folder path is correct and readable by the web server user.
- **Backups:** keep a copy of the books folder; the `data` folder can be rebuilt at any time with a rescan.

## 6. Possible Future Improvements

- Book cover images.
- Uploading books from other devices.
- Extra themes.
- Categories for books.
- A login page for added security.
