/*
    This file is part of Rekall.
    Copyright (C) 2013-2015

    Project Manager: Clarisse Bardiot
    Development & interactive design: Guillaume Jacquemin & Guillaume Marais (http://www.buzzinglight.com)

    This file was written by Guillaume Jacquemin.

    Rekall is a free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

#ifndef GLOBAL_H
#define GLOBAL_H

#include <QFileInfo>
#include <QCryptographicHash>
#include <QAction>
#include <QMap>
#include <QHashIterator>
#include <QDesktopServices>
#include <QDomNode>
#include <QDateTime>
#include <QDir>
#include <QDomElement>
#include <QProcess>
#include <QMenu>
#include <QWaitCondition>
#include "qmath.h"
#include "httprequest.h"
#include "httpresponse.h"
#include "httprequesthandler.h"
#include "http/udp.h"

class Metadatas : public QHash<QString, QString> {
public:
    inline QDomElement serialize(QDomDocument &xmlDoc) const {
        QDomElement xmlDocument = xmlDoc.createElement("document");
        QHashIterator<QString, QString> metadataIterator(*this);
        while (metadataIterator.hasNext()) {
            metadataIterator.next();
            QDomElement xmlMeta = xmlDoc.createElement("meta");
            xmlMeta.setAttribute("ctg", metadataIterator.key());
            xmlMeta.setAttribute("cnt",  metadataIterator.value());
            xmlDocument.appendChild(xmlMeta);
        }
        return xmlDocument;
    }
    inline void deserialize(const QDomElement &xmlElement) {
        QDomNode metadataNode = xmlElement.firstChild();
        while(!metadataNode.isNull()) {
            QDomElement metadataElement = metadataNode.toElement();
            if((!metadataElement.isNull()) && (metadataElement.nodeName() == "meta"))
                (*this)[metadataElement.attribute("ctg")] = metadataElement.attribute("cnt").replace("\r", "").replace("\n", "");

            metadataNode = metadataNode.nextSibling();
        }
    }
};

class WebWrapperInterface {
public:
    virtual void openWebPage(const QUrl &url, const QString &title = "", bool inBrowser = false) = 0;
};

class VideoPlayerInterface {
public:
    QUrl currentUrl;
};
class VideoPlayersInterface {
public:
    QList<VideoPlayerInterface*> players;
public:
    virtual void update(const QUrl &url, bool askClose = false, const QString &title = "", qint64 timecode = 0) = 0;
    virtual void seek(qint64 timecode) = 0;
    virtual void play(qint64 timecode = -1) = 0;
    virtual void pause() = 0;
    virtual void rewind(qint64 timecode = 0) = 0;
    virtual void forceClose() = 0;
};


class UserInfosInterface;
class ProjectInterface;
class HttpInterface;
class AnalyseInterface;
class RekallInterface;
class Global {
public:
    static QProcess *exifToolProcess;
    static UserInfosInterface *userInfos;
    static HttpInterface *http;
    static Udp *udp;
    static AnalyseInterface *analyse;
    static RekallInterface *rekall;
public:
    static QString configFileName;
    static QFileInfo pathApplication, pathDocuments, pathCurrent;
    static QList<ProjectInterface*> projects;

public:
    static QPair<QString,QString> seperateMetadata(const QString &metaline, const QString &separator = QString(":"));
    static QStringList splitQuotes(const QString &metaline, const QString &separator = QString(","));
    static Metadatas getMetadatas(QFileInfo file, ProjectInterface *project = 0, bool debug = false);
    static QString unicodeEscape(const QString &str);
    static QString getFileHash(const QFileInfo &file);
    static void revealInFinder(const QFileInfo &file);
    static void openFile(const QFileInfo &file);
    static void quickLook(const QFileInfo &file);
    static qreal getDuration(const QString &duration);
    static const QString timeToString(qreal time, bool millisec = false);
    static qreal stringToTime(const QString &timeStr);
    static void qSleep(int ms);
    static const QString dateToString(const QDateTime &date, bool addExactTime = false);
    static const QString plurial(qint16 value, const QString &text);

public:
    static WebWrapperInterface *webWrapper;
};


class VideoPlayerAsk {
public:
    explicit VideoPlayerAsk() { askClose = false; }
    explicit VideoPlayerAsk(ProjectInterface *_project, const QUrl &_url, bool _askClose = false, const QString &_title = "") {
        project  = _project;
        url      = _url;
        askClose = _askClose;
        title    = _title;
    }
public:
    ProjectInterface *project;
    QUrl url;
    bool askClose;
    QString title;
};

class RekallInterface {
public:
    bool trayIconWorking, newVersionOfRekall, firstTimeOpened;
    virtual void trayIconToOn(qint16 duration) = 0;
    virtual void trayIconToOff() = 0;

public:
    QImage screenshot;
    qint8 askScreenshot;
protected:
    virtual void takeScreenshot() = 0;

public:
    QList<VideoPlayerAsk> askVideoPlayer;

public:
    qint8 askAddProject;
    virtual void addProject() = 0;
    virtual void addProject(ProjectInterface *project) = 0;
    virtual void removeProject(ProjectInterface *project) = 0;
    virtual void updateGUI() = 0;
    virtual void syncSettings() = 0;
};


class SyncEntry;
class AnalyseInterface {
public:
    bool paused;
public:
    virtual void addToQueue(SyncEntry *file, ProjectInterface *project) = 0;
    virtual void stop() = 0;
};



class FileControllerInterface {
public:
    virtual void service(HttpRequest& request, HttpResponse& response, const QByteArray &contentType = QByteArray()) = 0;
};

class UserInfosInterface : public Metadatas {
public:
    virtual const QString   getAuthor()       const { return QString();                    }
    virtual const QDateTime getDateTime()     const { return QDateTime::currentDateTime(); }
    virtual const QString   getLocationGPS()  const { return QString();                    }
    virtual const QString   getLocationName() const { return QString();                    }
    virtual void setDockIcon(QWidget*, bool)        {   }
};



enum SyncAction { SyncDelete, SyncCreate, SyncUpdate };
class SyncEntry : public QHash<QString, SyncEntry*>, public QFileInfo {
public:
    explicit SyncEntry()                        : QFileInfo()         { isMacOsBundle = isBundle(); isInMacOsBundle = false; action = SyncCreate; userInfos = Global::userInfos; }
    explicit SyncEntry(const QString &filename) : QFileInfo(filename) { isMacOsBundle = isBundle(); isInMacOsBundle = false; action = SyncCreate; userInfos = Global::userInfos; }
    explicit SyncEntry(const QFileInfo &file)   : QFileInfo(file)     { isMacOsBundle = isBundle(); isInMacOsBundle = false; action = SyncCreate; userInfos = Global::userInfos; }

public:
    Metadatas metadatas;
    SyncAction action;
    UserInfosInterface *userInfos;
    bool isMacOsBundle, isInMacOsBundle;
public:
    bool isBundleForSure()  { return isMacOsBundle; }
    bool isIntoBundle()     { return isInMacOsBundle; }
    bool isFileOrBundle()   { return (( isBundleForSure()) || (isFile())); }
    bool isDirPure()        { return ((!isBundleForSure()) && (isDir())); }

public:
    inline const QString parentDir() const { return dir().absolutePath(); }
};



class SyncEntryEvent : public QObject {
    Q_OBJECT

public:
    SyncAction action;
    QAction *trayGlobalAction, *trayProjectAction;
    QFileInfo file;
    QString author, locationName, locationGPS;
    QString verbose;
    QDateTime dateTime;

public:
    explicit SyncEntryEvent(SyncEntry *file, QObject *parent);
    explicit SyncEntryEvent(const QDomElement &xmlElement, QObject *parent);

public:
    QDomElement serialize(QDomDocument &xmlDoc) const;
    void deserialize(const QDomElement &xmlElement);

public slots:
    void updateGUI();
    void trayMenuTriggered();
};


class HttpHost {
public:
    explicit HttpHost() {
        isOk        = false;
        isReachable = false;
    }
public:
    QString name, type;
    QString ip, broadcast;
    bool isOk, isReachable;
};
class HttpInterface : public QObject {
    Q_OBJECT

public:
    explicit HttpInterface(QObject *parent) : QObject(parent) { }
public:
    virtual quint16 getPort()         const = 0;
    virtual HttpHost getLocalHost()    const = 0;
    virtual HttpHost getExternalHost() const = 0;
    virtual QList<HttpHost> getRemoteHosts() const = 0;

};


class WatcherInterface;
class SyncEntryEvent;
class ProjectInterface : public QObject {
    Q_OBJECT

public:
    WatcherInterface *sync;
    QAction *trayMenuTitle, *trayMenuWeb, *trayMenuFolder, *trayMenuSeparator;
    QMenu *trayMenuEvents;
    QList<SyncEntryEvent*> events;
    VideoPlayersInterface *videoPlayers;

public:
    explicit ProjectInterface(QObject *parent = 0) : QObject(parent) {
        sync = 0;
        state = 0;
        videoPlayers = 0;
        isPublic  = true;
        isRemoved = false;
    }

public:
    FileControllerInterface* fileController;

public:
    QString name, friendlyName;
    quint16 state;
    QFileInfo path;
    bool isPublic, isRemoved;
    QDomDocument xmlDoc;
public:
    void setFriendlyName(const QString &_friendlyName) {
        friendlyName = _friendlyName;
        updateGUI();
    }

public slots:
    virtual void load() = 0;
    virtual void save() = 0;
    virtual void updateGUI() = 0;
    virtual void fileChanged(SyncEntry *file) = 0;
    virtual void projectChanged(SyncEntry *file, bool firstChange) = 0;
    virtual void projectChanged(const QString &xmlString) = 0;
};

class WatcherInterface : public QObject {
    Q_OBJECT

public:
    QHash<QString, SyncEntry> folders;

public:
    explicit WatcherInterface(ProjectInterface *_project) : QObject(_project) {
        project = _project;
    }
    virtual void start() = 0;

protected:
    ProjectInterface *project;

signals:
    void fileChanged(SyncEntry *file);
};


#endif // GLOBAL_H
